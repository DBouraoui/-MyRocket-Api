<?php

namespace App\EventListener;

use App\Event\UserRegistredEvent;
use App\Event\WebsiteContractEvent;
use App\Event\WebsiteCreateEvent;
use App\Event\WebsiteCredentialsEvent;
use App\service\EmailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

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
    public static function getSubscribedEvents(): array
    {
        return [
            UserRegistredEvent::NAME => 'onUserRegistered',
            WebsiteCreateEvent::NAME => 'onWebsiteCreate',
            WebsiteCredentialsEvent::NAME => 'onWebsiteCredentials',
            WebsiteContractEvent::NAME => 'onWebsiteContract',
        ];
    }

    public function onUserRegistered(userRegistredEvent $event) :void
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
            Throw new \Exception($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function onWebsiteContract(WebsiteContractEvent $event) :void
    {
        try {
            $user = $event->getUser();
            $websiteContract = $event->getWebsiteContract();

            $this->emailService->generate($user, "Un contrat vien d'être établie !",[
                "template"=>WebsiteContractEvent::TEMPLATE_NAME,
                "websiteContract"=>$websiteContract
            ]);

            $this->logger->info("Email de création de contrat ". $user->getEmail());
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            Throw new \Exception($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @throws \Exception
     */
    public function onWebsiteCreate(websiteCreateEvent $event): void
    {
        try {
            $user = $event->getUser();

            $context = [
                'template'=>WebsiteCreateEvent::TEMPLATE_NAME,
                'user'=>$user
            ];

            $this->emailService->generate($user, 'Du nouveau sur votre espace MyRocket !', $context);
            $this->logger->info("Email de création de site web envoyer à ". $user->getEmail());
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            Throw new \Exception($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @throws \Exception
     */
    public function onWebsiteCredentials(WebsiteCredentialsEvent $event): void
    {
        try {
            $user = $event->getUser();
            $website = $event->getWebsite();
            $configuration = $event->getConfiguration();

            $this->emailService->generate($user, 'Identifiants de connexion hebergeur',[
                'template'=>  WebsitecredentialsEvent::TEMPLATE_NAME,
                'urlWebsite'=>$website->getUrl(),
                'configuration'=> $configuration
            ]);

            $this->logger->info("Email contenant les informations de connexion hébergeur". $user->getEmail());
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            Throw new \Exception($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}