<?php

namespace App\service;

use App\Entity\User;
use App\traits\ExeptionTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailService
{
    use ExeptionTrait;
    public function __construct(private readonly LoggerInterface $logger,private readonly MailerInterface $mailer)
    {}

    /**
     * @throws \Exception
     */
    public function generate(User $user, string $subject, array $context) {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($_ENV['MAIL_ADDRESS']))
                ->to(new Address($user->getEmail()))
                ->subject($subject)
                ->htmlTemplate($context['template'].'.html.twig')
                ->locale('fr')
                ->context($context);

            $this->mailer->send($email);
        } catch (\Exception $e) {
          $this->logger->error($e->getMessage());
          Throw new \Exception($e->getMessage(),Response::HTTP_EXPECTATION_FAILED);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error($e->getMessage());
            Throw new \Exception($e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}