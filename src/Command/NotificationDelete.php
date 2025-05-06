<?php

namespace App\Command;

use App\Entity\Notification;
use App\Entity\Transaction;
use App\Event\TransactionCreateEvent;
use App\Event\TransactionRapportAdmin;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Repository\WebsiteContractRepository;
use App\service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:delete-notification',
    description: 'Supprime les notification de plus de 3 semaines',
)]
class NotificationDelete extends Command
{
    public function __construct(
        private readonly LoggerInterface        $logger,
        private readonly NotificationRepository $notificationRepository, private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Cette commande supprime les notification de plus de 3 semaines');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->logger->info('Check des notifications...');
            $output->writeln('Check des notifications...');

            $threeWeeksAgo = new \DateTime('now');
            $threeWeeksAgo->modify('-21 days');


            $notifications = $this->notificationRepository->createQueryBuilder('n')
                ->where('n.createdAt < :threeWeeks')
                ->andWhere('n.readingAt IS NULL')  // Correction de la condition de lecture
                ->setParameter('threeWeeks', $threeWeeksAgo)
                ->getQuery()
                ->getResult();

            $output->writeln(sprintf('Nombre de notifications trouvées : %d', count($notifications)));

            if (!empty($notifications)) {
                foreach ($notifications as $notification) {
                    $this->entityManager->remove($notification);
                }
                $this->entityManager->flush();
                $this->logger->warning('Notifications nettoyées');
            } else {
                $this->logger->warning('Aucune notification à nettoyer');
            }
            return Command::SUCCESS;
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}