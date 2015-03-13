<?php
require('./FuelSDK-PHP/ET_Client.php');

/**
 *
 *
 */
class ETMailer {
    protected $devMode;
    private $debugMode = true;

    private $client;
    private $TriggeredSendName = "AutoTriggeredEmail";
    private $retrievedTS;
    private $trigger;
    private $recipients;

    private $ETEmailID;
    private $emailName;
    private $messageBody;
    private $messageTextBody;
    private $messageBodyField = '%%[CustomContent]%%';
    private $messageSubject;
    private $messageSubjectField;

    private $emailTemplates = array('HTMLBody'=>'','TextBody'=>'');

    /**
     * @param $recipient - single email or simple array
     * @param $subject
     * @param $htmlBody
     * @param $textBody - if ommitted, use htmlBody stripped of tags
     *
     */
    public function __construct($recipients = '', $htmlBody = '', $textBody = '')
    {
        if($this->debugMode) {
            echo  "<pre>";
        }

        $this->client = new ET_Client();
        $this->trigger = new ET_TriggeredSend();
        $this->trigger->authStub = $this->client;

        $this->setMessageBody($htmlBody, $textBody);
        // $this->setMessageSubject($subject);
        $this->recipients = $recipients;

    }

    public function executeSend()
    {
        if(!$this->retrieveTriggeredSend()) {
            throw new Exception("Error: Triggered send definition could not be retrieved");
        }

        if($this->retrievedTS->TriggeredSendStatus != "Inactive") {
            $this->setTriggeredSendInactive();
        }

        $this->setSubscribers($this->recipients);
        $this->fetchEmail();
        $this->setEmailContent();
        if(!$this->startTriggeredSend()) {
            throw new Exception("Error: Could not initiate triggered send");
        }

        $this->sendTriggeredSendEmail();
        $this->setEmailContent(true); //restore template
    }

    /**
     * @param array $emailArray : csv email list
     */
    public function setSubscribers($emailArray)
    {
        $subscribers = array();
        if(!is_array($emailArray)) {
            $emailList = explode(',',$emailArray);
            $emailArray = array();
            foreach($emailList as $email) {
                $emailArray[] = array('email' => $email);
            }
        }

        foreach ($emailArray as $recipientObject) {
            $attributes = array();
            if(array_key_exists('attributes',$recipientObject) && is_array($recipientObject['attributes'])) {
                foreach ($recipientObject['attributes'] as $name => $value) {
                    $attributes[] = array('Name' => $name, 'Value' => $value);
                }
            }

            $subscribers[] = array(
                "EmailAddress" => $recipientObject['email'],
                "SubscriberKey" => $recipientObject['email'],
                "Attributes" => $attributes,
            );
        }

        if($this->debugMode) {
            echo "Subscribers: " . print_r($subscribers, true) . "\n";
        }

        $this->trigger->subscribers = $subscribers;

    }

    /**
     * @param $emailName - email key index in ExactTarget
     */
    public function setEmailName($emailName)
    {
        $this->emailName = $emailName;
    }

    public function setETEmailID($emailID)
    {
        $this->ETEmailID = $emailID;
    }

    /**
     * Change the replacement text tag to a custom value
     *
     * @param string $replacementTag
     */
    public function setMessageBodyField($replacementTag)
    {
        $this->messageBodyField = $replacementTag;
    }

    /**
     * @param $TSName - key index in ExactTarget to retrieve a triggered send object
     */
    public function setTriggeredSendName($TSName)
    {
        $this->TriggeredSendName = $TSName;
    }

    /**
     * @param $htmlBody
     * @param $textBody - if ommitted, use htmlBody stripped of tags
     * @param $replacementField - the text in the email template to be replace by content
     */
    public function setMessageBody($htmlBody, $textBody = '', $replacementField = "%%[CustomContent]%%")
    {
        $this->messageBody = $htmlBody;
        $this->messageTextBody = empty($textBody) ? strip_tags($htmlBody) : $textBody;
        $this->messageBodyField = $replacementField;
    }

    public function setMessageSubject($messageSubject)
    {
        $this->messageSubject = $messageSubject;
    }

    /**
     * Retrieve a single triggered send object
     * @return stdClass Object
       (
            [PartnerKey] =>
            [ObjectID] =>
            [CustomerKey] => AutoTriggeredEmail
            [Name] => AutoTriggeredEmail
            [TriggeredSendStatus] => Inactive
       )
       boolean false on failure
    */
    public function retrieveTriggeredSend()
    {
        try {
            $this->trigger->props = array("CustomerKey", "Name", "TriggeredSendStatus");
            $this->trigger->filter = array(
                'Property' => 'CustomerKey',
                'SimpleOperator' => 'equals',
                'Value' => $this->TriggeredSendName
            );

            $getResult = $this->trigger->get();
            $getStatus = $getResult ? true : false;
            $this->retrievedTS = $getResult->results[0];

            if($this->debugMode) {
                print 'Code: '.$getResult->code."\n";
                print 'Message: '.$getResult->message."\n";
                print 'Result Count: '.count($getResult->results)."\n";
                print 'Results: '."\n";
                print_r($getResult->results);
                print "\n---------------\n";
            }

            if($getStatus) {
                return $getResult->results;
            } else {
                return false;
            }
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
    }

    /**
     * Triggered send cannot be active when assigning recipient list
     */
    private function setTriggeredSendInactive()
    {
        $this->trigger->props = array(
            'CustomerKey' => $this->TriggeredSendName,
            'TriggeredSendStatus' => 'Inactive' );

        return $this->trigger->patch();
    }

    /**
     * Start a TriggeredSend by setting to Active
     */
    public function startTriggeredSend()
    {
        if($this->retrievedTS->TriggeredSendStatus != "Inactive") {
            $this->setTriggeredSendInactive();
        }

        try {
            $this->trigger->props = array(
                'CustomerKey' => $this->TriggeredSendName,
                'TriggeredSendStatus' => 'Active',
                'RefreshContent'=>'true',
                // 'Subject' => $this->messageSubject,
                // 'Department' => 'TCC',
            );

            $patchResult = $this->trigger->patch();
            $patchStatus = $patchResult->status ? true : false;
            $status = $patchResult->status;

            if($this->debugMode) {
                print 'Patch Status: '.print_r($status,true)."\n";
                print 'Code: '.$patchResult->code."\n";
                print 'Message: '.$patchResult->message."\n";
                print 'Result Count: '.count($patchResult->results)."\n";
                print 'Results: '."\n";
                print_r($patchResult->results);
                print "\n---------------\n";
            }

            return $status;

        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
    }

    /**
     * Commence execution of Triggered Send
     */
    public function sendTriggeredSendEmail()
    {
        try {

            $sendResult = $this->trigger->send();

            if($this->debugMode) {
                print_r('Send Status: '.($sendResult->status ? 'true' : 'false')."\n");
                print 'Code: '.$sendResult->code."\n";
                print 'Message: '.$sendResult->message."\n";
                print 'Results: '."\n";
                print_r($sendResult->results);
                print "\n---------------\n";
            }

            return $sendResult->status;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
    }

    /**
     * Create Triigered send def with uuid name, intended for subsequent deletion
     * @param $emailId
     */
    /*
    public function createTriggeredSendDef($emailId)
    {
        try {
            $this->TriggeredSendName = uniqid();
            $this->trigger = new ET_TriggeredSend();
            $this->trigger->authStub = $this->client;

            $this->trigger->props = array(
                'CustomerKey' => $this->TriggeredSendName,
                'Name' => $this->TriggeredSendName,
                'Email' => array("ID"=>"$emailId"),
                "SendClassification"=> array("CustomerKey"=> $this->customerKey)
            );

            $postResult = $postTrig->post();
            $postStatus = $postResult->status ? true : false;

            if($this->debugMode) {
                print 'Post Status: '.($postStatus ? 'true' : 'false')."\n";
                print 'Code: '.$postResult->code."\n";
                print 'Message: '.$postResult->message."\n";
                print 'Result Count: '.count($postResult->results)."\n";
                print 'Results: '."\n";
                print_r($postResult->results);
                print "\n---------------\n";
            }

            return $postStatus;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
    }

    /**
     * Retrieve email so subject and content body can be set
     * @return bool on fail | message object
     */
    private function fetchEmail()
    {
        try {
            $getEmail = new ET_Email();
            $getEmail->authStub = $this->client;

            $getEmail->filter = array(
                'Property' => 'Name',
                'SimpleOperator' => 'equals',
                'Value' => $this->emailName
            );

            $getEmail->props = array("Name","HTMLBody","TextBody","Subject","CustomerKey");

            $getResponse = $getEmail->get();

            if($this->debugMode) {
                print_r('Get Status: '.($getResponse->status ? 'true' : 'false')."\n");
                print "Filter: --- " . print_r($getEmail->filter, true) . " --- \n";
                print 'Code: '.$getResponse->code."\n";
                print 'Message: '.$getResponse->message."\n";
                print_r('More Results: '.($getResponse->moreResults ? 'true' : 'false')."\n");
                print 'Results Length: '. count($getResponse->results)."\n";
                print 'Results: '."\n";
                print_r($getResponse->results);
                print "\n---------------\n";
            }

            if(is_array($getResponse->results)) {
                $this->emailTemplates['HTMLBody'] = $getResponse->results[0]->HTMLBody;
                $this->emailTemplates['TextBody'] = $getResponse->results[0]->TextBody;

                return true;
            } else {
                throw new Exception("Email not found. Did you create it in Exact Target?", 404);
            }
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }

    }

    /**
     * Retrieves email from ET by email name, replace the 'messageBodyField' text with actual content
     * @return boolean
     */
    private function setEmailContent($restoreTemplate = false)
    {
        if($this->debugMode) {
            print "Updates Email \n";
        }

        if(!$restoreTemplate) {
            $htmlBody = str_replace($this->messageBodyField, $this->messageBody, $this->emailTemplates['HTMLBody']);
            $textBody = str_replace($this->messageBodyField, $this->messageTextBody, $this->emailTemplates['TextBody']);
        } else {
            $htmlBody = $this->emailTemplates['HTMLBody'];
            $textBody = $this->emailTemplates['TextBody'];
        }

        try{
            $patchEmail = new ET_Email();
            $patchEmail->authStub = $this->client;
            $patchEmail->props = array(
                "ID" => $this->ETEmailID,
                "CustomerKey" => $this->emailName,
                "Name"=> $this->emailName,
                // "Subject"=> $this->messageSubject,
                "HTMLBody"=> $htmlBody,
                "TextBody"=>$textBody,
            );
            $patchResult = $patchEmail->patch();

            if($this->debugMode) {
                print_r('Patch Status: '.($patchResult->status ? 'true' : 'false')."\n");
                print 'Code: '.$patchResult->code."\n";
                print 'Message: '.$patchResult->message."\n";
                print 'Results Length: '. count($patchResult->results)."\n";
                print 'Results: '."\n";
                print_r($patchResult->results);
                print "\n---------------\n";
            }

            return $patchResult->status;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
    }

}