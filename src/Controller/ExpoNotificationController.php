<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


//#[Route('/notification')]
final class ExpoNotificationController extends AbstractController
{
//    #[Route('/{id}/send', name: 'app_notification_send', methods: ['GET','POST'])]
//    public function expoNotification(
//        int $id,
//        ApplicationErrorService $applicationErrorService,
//        ExpoNotificationService $expoNotificationService,
//        EntityManagerInterface $entityManager,
//        LoggerInterface $logger
//    ): Response
//    {
//        $notification = $entityManager->getRepository(Notification::class)->find($id);
//
//        if (!$notification) {
//            $this->addFlash('error', NotificationErrorCodes::NOTIFICATION_NOT_FOUND['message']);
//            $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_NOT_FOUND, [
//                'notification_id' => $id,
//            ]);
//            return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
//        }
//
///*        $company = $notification->getCompany();
//
//        if (!$company) {
//            $this->addFlash('error', NotificationErrorCodes::NOTIFICATION_NO_COMPANY['message']);
//            $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_NO_COMPANY, [
//                'notification_id' => $notification->getId(),
//            ]);
//        }*/
//
//        $companies = $notification->getCompanies();
//
//        if (!$companies) {
//            $this->addFlash('error', NotificationErrorCodes::NOTIFICATION_NOT_FOUND['message']);
//            $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_NO_COMPANY, [
//                'notification_id' => $id,
//            ]);
//        }
//
//        $users = [];
//        foreach ($companies as $company) {
//            array_merge($users, $company->getUsers()->toArray());
//        }
//
//        if (empty($users)) {
//            $this->addFlash('error', NotificationErrorCodes::NOTIFICATION_NO_USERS['message']);
//            $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_NO_USERS, [
//                'company_id' => $companies[0]->getId(),
//            ]);
//        }
//
//        $deviceTokens = [];
//        foreach ($users as $user) {
//            $tokens = $user->getDeviceTokens()->toArray();
//
//            foreach ($tokens as $token) {
//                $this->get('logger')->info("Resultado de la notificación Expo", $token);
//
//                $deviceTokens[] = $token->getToken();
//            }
//
//        }
//
//        if (empty($deviceTokens)) {
//            $this->addFlash('error', NotificationErrorCodes::NOTIFICATION_NO_USERS_TOKENS['message']);
//            $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_NO_USERS_TOKENS, [
//                'company_id' => $company->getId(),
//            ]);
//
//        }
//
//        $result = $expoNotificationService->sendExpoNotification($deviceTokens,
//                                                                $notification->getTitle(),
//                                                                $notification->getMessage());
//
//        if ($result['success']) {
//            $this->addFlash('success', 'Notificaciónes enviada correctamente');
//        } else {
//            $this->addFlash('error', NotificationErrorCodes::NOTIFICATION_SEND_FAILED['message']);
//            $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_SEND_FAILED, [
//                'notification_id' => $notification->getId(),
//            ]);
//        }
//
//        return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
//
//    }
}