<?php

namespace App\Command;

use App\Event\ResendInvoiceEvent;
use App\Event\ResendInvoiceRapportAdminEvent;
use App\Repository\UserRepository;
use App\Repository\WebsiteContractRepository;
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
        private readonly LoggerInterface           $logger,
        private readonly WebsiteContractRepository $websiteContractRepository,
        private readonly EventDispatcherInterface  $eventDispatcher,
        private readonly UserRepository $userRepository
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
            $sevenDaysAgo->modify('-7 days');

            $websiteContracts = $this->websiteContractRepository->createQueryBuilder('c')
                ->where('c.nextPaymentAt < :sevenDaysAgo')
                ->setParameter('sevenDaysAgo', $sevenDaysAgo)
                ->getQuery()
                ->getResult();

            $output->writeln(sprintf('Nombre de contrats trouvés : %d', count($websiteContracts)));

            if (!empty($websiteContracts)) {
                $data = [];

                foreach ($websiteContracts as $contract) {
                    $user = $contract->getUser();
                    $data[] = $contract;

                    $event = new ResendInvoiceEvent($user, $contract);
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
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}