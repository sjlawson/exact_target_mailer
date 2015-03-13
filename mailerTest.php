<?php
require('ETMailer.class.php');

$subject = "My subject";
$body = 'Custom message description<br />HELLO World! <p>A paragraph</p>
Added back in the inner-replacement text instead of full body replacement<br /><hr />
<b>Some HTML Content Goes here. NOW WITH NEW CONTENT</b>\n
                        <table><tr><td>A table</td></tr></table>
';


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


$sendObj = new ETMailer($recipients, $body);
$sendObj->executeSend();
