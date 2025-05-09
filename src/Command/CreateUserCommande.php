<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a new user manually.',
)]
class CreateUserCommande extends Command
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email of the user (e.g., dylan.bouraoui@epitech.eu)')
            ->addArgument('companyName', InputArgument::REQUIRED, 'Name of the company (e.g., MyRocket)')
            ->addArgument('postCode', InputArgument::REQUIRED, 'Postcode (e.g., 83500)')
            ->addArgument('city', InputArgument::REQUIRED, 'City (e.g., Paris)')
            ->addArgument('country', InputArgument::REQUIRED, 'Country (e.g., France)')
            ->addArgument('address', InputArgument::REQUIRED, 'Address (e.g., 35 rue Paul Verlaine)')
            ->addArgument('phone', InputArgument::REQUIRED, 'Phone number (e.g., 0609216908)')
            ->addArgument('password', InputArgument::REQUIRED, 'Password for the user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        $companyName = $input->getArgument('companyName');
        $password = $input->getArgument('password');
        $postCode = $input->getArgument('postCode');
        $city = $input->getArgument('city');
        $country = $input->getArgument('country');
        $phone = $input->getArgument('phone');
        $address = $input->getArgument('address');

        $user = new User();
        $user->setEmail($email);
        $user->setCompanyName($companyName);
        $user->setPhone($phone);
        $user->setAddress($address);
        $user->setPostCode($postCode);
        $user->setCity($city);
        $user->setCountry($country);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('<info>User successfully created!</info>');

        return Command::SUCCESS;
    }
}
