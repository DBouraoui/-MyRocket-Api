<?php

namespace App\Command;

use App\Entity\Notification;
use App\Entity\Transaction;
use App\Event\TransactionCreateEvent;
use App\Event\TransactionRapportAdmin;
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
    name: 'app:check-invoice-user',
    description: 'Vérifie les factures du jour à payer et crée des factures',
)]
class CheckInvoiceUser extends Command
{
    public function __construct(
        private readonly LoggerInterface           $logger,
        private readonly WebsiteContractRepository $websiteContractRepository,
        private readonly EntityManagerInterface    $entityManager,
        private readonly EventDispatcherInterface  $eventDispatcher,
        private readonly UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Cette commande vérifie les contrats dont la date de paiement est due et crée des transactions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->logger->info('Check des factures à payer...');
            $output->writeln('Vérification des factures à payer...');

            $today = new \DateTime('today');
            $tomorrow = new \DateTime('tomorrow');

            $websiteContracts = $this->websiteContractRepository->createQueryBuilder('c')
                ->where('c.nextPaymentAt >= :today')
                ->andWhere('c.nextPaymentAt < :tomorrow')
                ->setParameter('today', $today)
                ->setParameter('tomorrow', $tomorrow)
                ->getQuery()
                ->getResult();

            $output->writeln(sprintf('Nombre de contrats trouvés : %d', count($websiteContracts)));

            if (!empty($websiteContracts)) {
                $data = [];

                foreach ($websiteContracts as $contract) {
                    $user = $contract->getUser();

                    $transaction = new Transaction();
                    $transaction->setUser($user);
                    $transaction->setWebsiteContract($contract);
                    $transaction->setTva($contract->getTva());
                    $transaction->setAmount($contract->getMonthlyCost());
                    $transaction->setIsPaid(false);

                    $contract->setNextPaymentAt(new \DateTimeImmutable('+30 days'));
                    $contract->setLastPaymentAt(new \DateTimeImmutable());

                    $data[] = $transaction;

                    $this->entityManager->persist($transaction);
                    $this->entityManager->flush();

                    $notification = new Notification();
                    $notification->setUser($user);
                    $notification->setTitle(NotificationService::TRANSACTION_CREATED_TITLE);
                    $notification->setDescription(NotificationService::TRANSACTION_CREATED_DESCRIPTION);
                    $notification->setIsPriotity(true);

                    $this->entityManager->persist($notification);
                    $this->entityManager->flush();

                    $event = new TransactionCreateEvent($user, $transaction);
                    $this->eventDispatcher->dispatch($event, TransactionCreateEvent::NAME);

                    $this->logger->info('Facture envoyée pour ' . $user->getEmail());
                    $output->writeln('Facture envoyée pour ' . $user->getEmail());
                }

                $user = $this->userRepository->findOneBy(['email' => 'dylan.bouraoui@epitech.eu']);

                $event = new TransactionRapportAdmin($data,$user);
                $this->eventDispatcher->dispatch($event, TransactionRapportAdmin::NAME);
            } else {
                $this->logger->warning('Aucune facture en à envoyer');
            }


            return Command::SUCCESS;
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}