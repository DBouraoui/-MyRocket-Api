<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber;

use App\Event\ResendInvoiceEvent;
use App\Event\ResendInvoiceRapportAdminEvent;
use App\Event\TransactionCreateEvent;
use App\Event\TransactionRapportAdmin;
use App\Event\UserRegistredEvent;
use App\Event\WebsiteContractEvent;
use App\Event\WebsiteCreateEvent;
use App\Event\WebsiteCredentialsEvent;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class EmailNotifierEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EmailService $emailService,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserRegistredEvent::NAME => 'onUserRegistered',
            WebsiteCreateEvent::NAME => 'onWebsiteCreate',
            WebsiteCredentialsEvent::NAME => 'onWebsiteCredentials',
            WebsiteContractEvent::NAME => 'onWebsiteContract',
            TransactionCreateEvent::NAME => 'onTransactionCreate',
            TransactionRapportAdmin::NAME => 'onTransactionRapportAdmin',
            ResendInvoiceEvent::NAME => 'onResendInvoice',
            ResendInvoiceRapportAdminEvent::NAME => 'onResendInvoiceRapportAdmin',
        ];
    }

    public function onResendInvoice(ResendInvoiceEvent $event): void
    {
        try {
            $context = [
                'template' => ResendInvoiceEvent::TEMPLATE_NAME,
                'user' => $user = $event->getUser(),
                'transaction' => $event->getTransactions(),
            ];

            $this->emailService->generate(
                $user,
                'rappel une facture est disponible sur votre espace client',
                $context
            );

            $this->logger->info('Relance facture impayer envoyer à '. $user->getEmail());
        } catch (\Exception|\Throwable $e) {
            $this->logger->error($e->getMessage());
        }
    }

    public function onResendInvoiceRapportAdmin(ResendInvoiceRapportAdminEvent $event): void
    {
        try {
            $user = $event->getUser();
            $websiteContract = $event->getWebsiteContract();

            $context = [
                'template' => ResendInvoiceRapportAdminEvent::TEMPLATE_NAME,
                'user' => $user,
                'transactions' => $websiteContract,
            ];
            $this->emailService->generate($user, 'Rapport d\'envoie des facture impayer', $context);

            $this->logger->info("Rapport des facture impayer envoeyr a l'admin ". $user->getEmail());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @throws \Exception
     */
    public function onTransactionCreate(TransactionCreateEvent $event): void
    {
        try {
            $user = $event->getUser();
            $transaction = $event->getTransaction();

            $context = [
                'user' => $user,
                'transaction' => $transaction,
                'template' => TransactionCreateEvent::TEMPLATE_NAME,
            ];

            $this->emailService->generate(
                $user,
                'Une facture est arriver sur votre espace',
                $context
            );
            $this->logger->info('Facture envoyer à '. $user->getEmail());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \Exception($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function onTransactionRapportAdmin(TransactionRapportAdmin $event): void
    {
        try {
            $data = $event->getData();
            $user = $event->getUser();

            $context = [
                'template' => TransactionRapportAdmin::TEMPLATE_NAME,
                'transactions' => $data,
            ];

            $this->emailService->generate(
                $user,
                'Rapport d\'envoie des facture',
                $context
            );

            $this->logger->info("Rapport de facture envoyer à l'admin ". $user->getEmail());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \Exception($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @throws \Exception
     */
    public function onUserRegistered(UserRegistredEvent $event): void
    {
        try {
            $user = $event->getUser();

            $context = [
                'emailUser' => $user->getEmail(),
                'passwordUser' => $event->getUser()->getPlainPassword(),
                'loginUrl' => 'http://login.fr',
                'template' => 'register',
            ];

            $this->emailService->generate($user, 'Votre compte MyRocket est prêt !', $context);
            $this->logger->debug('Email de bienvenu envoyer à '. $user->getEmail());
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function onWebsiteContract(WebsiteContractEvent $event): void
    {
        try {
            $user = $event->getUser();
            $websiteContract = $event->getWebsiteContract();

            $this->emailService->generate($user, "Un contrat vien d'être établie !", [
                'template' => WebsiteContractEvent::TEMPLATE_NAME,
                'websiteContract' => $websiteContract,
            ]);

            $this->logger->info('Email de création de contrat '. $user->getEmail());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \Exception($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @throws \Exception
     */
    public function onWebsiteCreate(WebsiteCreateEvent $event): void
    {
        try {
            $user = $event->getUser();

            $context = [
                'template' => WebsiteCreateEvent::TEMPLATE_NAME,
                'user' => $user,
            ];

            $this->emailService->generate($user, 'Du nouveau sur votre espace MyRocket !', $context);
            $this->logger->info('Email de création de site web envoyer à '. $user->getEmail());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \Exception($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
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

            $this->emailService->generate($user, 'Identifiants de connexion hebergeur', [
                'template' => WebsiteCredentialsEvent::TEMPLATE_NAME,
                'urlWebsite' => $website->getUrl(),
                'configuration' => $configuration,
            ]);

            $this->logger->info('Email contenant les informations de connexion hébergeur'. $user->getEmail());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \Exception($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
