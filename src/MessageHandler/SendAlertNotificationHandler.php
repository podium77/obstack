<?php
namespace App\MessageHandler;

use App\Message\SendAlertNotificationMessage;
use App\Service\NotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendAlertNotificationHandler
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(SendAlertNotificationMessage $message)
    {
        $alert = $message->getAlert();
        $this->notificationService->notifyAlert(
            $alert,
            $message->getMessage(),
            $message->getType()
        );
    }
}
