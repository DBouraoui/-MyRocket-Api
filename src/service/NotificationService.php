<?php

namespace App\service;

use App\Entity\Notification;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

 class NotificationService {

    public const WEBSITE_CONTRACT_CREATED_TITLE = "Nouveau contrat créé !";
    public const WEBSITE_CONTRACT_CREATED_DESCRIPTION = "Un contrat vient d'être créé. Vous pouvez le consulter dans votre espace 'site web' dès maintenant.";

    public const WEBSITE_CREATED_TITLE = "Un nouveau site web est en route...";
    public const WEBSITE_CREATED_DESCRIPTION = "Un site web a été ajouté à votre espace client.";

    public const WEBSITE_CONFIG_CREATED_TITLE = "Accès serveur configuré";
    public const WEBSITE_CONFIG_CREATED_DESCRIPTION = "Vous pouvez maintenant demander vos identifiants de connexion à votre hébergeur.";

    public const WEBSITE_MAINTENANCE_CONTRACT_CREATED_TITLE = "Contrat de maintenance activé";
    public const WEBSITE_MAINTENANCE_CONTRACT_CREATED_DESCRIPTION = "Un contrat de maintenance vient d'être mis en place pour votre site.";

    public const TRANSACTION_CREATED_TITLE = "Nouvelle facture disponible";
    public const TRANSACTION_CREATED_DESCRIPTION = "Retrouvez votre facture dans votre espace dédié dès à présent.";

    public const TRANSACTION_DELAY_TITLE = "Facture en attente de paiement";
    public const TRANSACTION_DELAY_DESCRIPTION = "Il ne vous reste plus que quelques jours pour régler votre facture.";

    public function __construct
    (
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager
    )
    {
    }

    public function valideNotification(Notification $notification) {
        try {
            $notification->setIsReading(true);
            $notification->setReadingAt(new \DateTimeImmutable('now'));

            $this->entityManager->flush();
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            Throw new \Exception($e->getMessage(),Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function valideAllNotifications(Collection $notifications) {
        try {
            foreach ($notifications as $notification) {
                $this->valideNotification($notification);
            }

        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            Throw new \Exception($e->getMessage(),Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
    public function normalize(Notification $notification): array
    {
        return [
            'uuid'=>$notification->getUuid(),
            'title'=>$notification->getTitle(),
            'description'=>$notification->getDescription(),
            'createdAt'=>$notification->getCreatedAt()->format('d-m-Y H:i:s'),
            'isPriority'=>$notification->isPriotity(),
            'readingAt'=>$notification->getReadingAt()?->format('d-m-Y H:i:s'),
        ];
    }

    public function normalizes(Collection $notifications): array
    {
        $notif = [];

        foreach ($notifications as $notification) {
            $notif[] = $this->normalize($notification);
        }

        return $notif;
    }
}
