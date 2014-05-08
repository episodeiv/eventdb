<?php

class EventDB_Events_Event_MailMeAction extends EventDBBaseAction
{
    public function isSecure()
    {
        return true;
    }

    public function getCredentials()
    {
        return array ('icinga.user');
    }

    public function getDefaultViewName()
    {
        return 'Success';
    }

    public function executeRead(AgaviParameterHolder $rd)
    {
        $event = $rd->getParameter('event');
        $event->loadReference('comments');
        $event = $event->toArray(true);
        $mail = $this->renderMail($event);
        $this->sendmail(
            'New event from ' . $event['host_name'] . ' created at ' . $event['created'],
            $mail,
            $this->context->getUser()->getNsmUser()->user_email
        );
        return $this->getDefaultViewName();
    }

    public function executeWrite(AgaviParameterHolder $rd)
    {
        return $this->getDefaultViewName();
    }

    public function handleError(AgaviRequestDataHolder $rd)
    {
        return $this->getDefaultViewName();
    }

    private function renderMail($event)
    {
        $template = AgaviConfig::get('core.module_dir') . '/EventDB/config/mail.phtml';
        ob_start();
        include $template;
        $mail = ob_get_clean();
        return $mail;
    }

    private function sendmail($subject, $mail, $to)
    {
        $headers = array(
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: dontreply@localhost.localdomain',
            'X-Mailer: PHP/' . phpversion(),
            'Date: ' . date('r')
        );
        mail($to, $subject, $mail, implode("\r\n", $headers));
    }
}
