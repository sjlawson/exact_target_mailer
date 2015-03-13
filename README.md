# exact_target_mailer
Wrapper class for Exact Target FuelSDK

For use with triggered emails. 
Allows one to add a custom replacement text tag for changing a large portion of the email body.

In your ExactTarget account, create an email template with a custom tag: %%[CustomContent]%%

### edit ./FuekSDK/config.php and set your clientid and clientsecret

I've removed the ability to set subject since subject is set in the TriggeredSend feature on Exact Target. 
It could easily be added back in, and the setter method is still in ETMailer.class.php

Execute send with:
<pre>
$sendObj = new ETMailer($recipients, $body);
$sendObj->setTriggeredSendName('TriggeredSendName you set up in ExactTarget'); -- default is 'AutoTriggeredEmail'
$sendObj->setMessageBodyField('%%[CustomContent]%%'); // optional - default value displayed 
$sendObj->executeSend();
</pre>

$body is a text block or string, HTML allowed
$recipients is an array of the following format:

<pre>
$recipients = array(   
    array(
        'email' => 'you@youremail.com',
        'attributes' => array('Title' => 'Master of all I survey')
    ),
    array(
        'email' => 'you@yourotheremail.com',
        'attributes' => array(
            'Title' => 'Guitar Nobility',
            'Description' => 'Master of Music'
        )
    )
);
</pre>
