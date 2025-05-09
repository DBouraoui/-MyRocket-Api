<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use App\Entity\Notification;
use App\Event\ResendInvoiceEvent;
use App\Event\ResendInvoiceRapportAdminEvent;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:resend-invoice-user',
    description: 'Vérifie les factures impayées et relance l\'utilisateur',
)]
class ResendEmailInvoiceUser extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UserRepository $userRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Cette commande vérifie les contrats dont la date de paiement est dépasser et relance les utilisateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->logger->info('Check des factures impayées...');
            $output->writeln('Vérification des factures impayées...');

            $sevenDaysAgo = new \DateTime('today');
            $sevenDaysAgo->modify('-6 days');

            $transactions = $this->transactionRepository->createQueryBuilder('t')
                ->where('t.createdAt <= :sevenDaysAgo')
                ->andWhere('t.isPaid = :isPaid')
                ->setParameter('sevenDaysAgo', $sevenDaysAgo)
                ->setParameter('isPaid', false)
                ->getQuery()
                ->getResult()
            ;

            $output->writeln(sprintf('Nombre de contrats trouvés : %d', count($transactions)));

            if (!empty($transactions)) {
                $data = [];

                foreach ($transactions as $transaction) {
                    $user = $transaction->getUser();
                    $data[] = $transaction;

                    $transaction->setIsReminderSent(true);
                    $transaction->setReminderSentAt(new \DateTimeImmutable('now'));
                    $this->entityManager->flush();

                    $notification = new Notification();
                    $notification->setUser($user);
                    $notification->setTitle(NotificationService::TRANSACTION_DELAY_TITLE);
                    $notification->setDescription(NotificationService::TRANSACTION_DELAY_DESCRIPTION);
                    $notification->setIsPriotity(true);

                    $this->entityManager->persist($notification);
                    $this->entityManager->flush();

                    $event = new ResendInvoiceEvent($user, $transaction);
                    $this->eventDispatcher->dispatch($event, ResendInvoiceEvent::NAME);

                    $this->logger->info('Relance de facture impayer à ' . $user->getEmail());
                    $output->writeln('Relance envoyée pour ' . $user->getEmail());
                }

                $user = $this->userRepository->findOneBy(['email' => 'dylan.bouraoui@epitech.eu']);

                $event = new ResendInvoiceRapportAdminEvent($user, $data);
                $this->eventDispatcher->dispatch($event, ResendInvoiceRapportAdminEvent::NAME);
            } else {
                $this->logger->warning('Aucune relance client à été éffectuer');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
