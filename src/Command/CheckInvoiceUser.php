<?php

namespace App\Command;

use App\Entity\Transaction;
use App\Event\TransactionCreateEvent;
use App\Repository\WebsiteContractRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:check-invoice-user',
    description: 'Vérifie les factures impayées et crée des transactions',
)]
class CheckInvoiceUser extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly WebsiteContractRepository $websiteContractRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher
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
            $this->logger->info('Check des factures impayées...');
            $output->writeln('Vérification des factures impayées...');

            $websiteContracts = $this->websiteContractRepository->createQueryBuilder('c')
                ->where('c.nextPaymentAt <= :today')
                ->setParameter('today', new \DateTime('today'))
                ->getQuery()
                ->getResult();

            $output->writeln(sprintf('Nombre de contrats trouvés : %d', count($websiteContracts)));

            foreach ($websiteContracts as $contract) {
                $user = $contract->getUser();

                $transaction = new Transaction();
                $transaction->setUser($user);
                $transaction->setWebsiteContract($contract);
                $transaction->setTva($contract->getTva());
                $transaction->setAmount(($contract->getAnnualCost() / 12));

                $contract->setNextPaymentAt(new \DateTimeImmutable('+30 days'));
                $contract->setLastPaymentAt(new \DateTimeImmutable());

                $this->entityManager->persist($transaction);
                $this->entityManager->flush();


                $event = new TransactionCreateEvent($user, $transaction);
                $this->eventDispatcher->dispatch($event, TransactionCreateEvent::NAME);

                $this->logger->info('Facture envoyée pour ' . $user->getEmail());
                $output->writeln('Facture envoyée pour ' . $user->getEmail());
            }

            return Command::SUCCESS;
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}