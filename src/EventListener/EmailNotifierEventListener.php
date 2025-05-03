<?php

namespace App\EventListener;

use App\Event\UserRegistredEvent;
use App\service\EmailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EmailNotifierEventListener implements EventSubscriberInterface {

    private EmailService $emailService;
    private LoggerInterface $logger;

    public function __construct(
        EmailService $emailService,
        LoggerInterface $logger
    ) {
        $this->emailService = $emailService;
        $this->logger = $logger;
    }
    public static function getSubscribedEvents()
    {
        return [
            UserRegistredEvent::NAME => 'onUserRegistered',
        ];
    }

    public function onUserRegistered(userRegistredEvent $event)
    {
        try {
            $user = $event->getUser();
            $password = $event->getPassword();

            $context = [
                'emailUser'=>$user->getEmail(),
                'passwordUser'=>$password,
                'loginUrl'=> 'http://login.fr',
                'template'=>'register'
            ];

            $this->emailService->generate($user, 'Votre compte MyRocket est prêt !',$context);
            $this->logger->info("Email de bienvenu envoyer à ". $user->getEmail());
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            return;
        }
    }
}