<?php

namespace App\Service;

use App\Entity\App\User;
use App\Entity\App\UserActivity;
use App\Service\TenantManager;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

class UserActivityService
{
    private TenantManager $tenantManager;
    private LoggerInterface $logger;

    public function __construct(
        TenantManager $tenantManager,
        LoggerInterface $logger
    ) {
        $this->tenantManager = $tenantManager;
        $this->logger = $logger;
    }

    /**
     * Log user activity
     *
     * @param User $user The user performing the action
     * @param string $action The action being performed (login, logout, view_page, etc.)
     * @param string|null $details Additional details about the action
     * @param Request|null $request The HTTP request for IP and user agent tracking
     */
    public function logActivity(User $user, string $action, ?string $details = null, ?Request $request = null): void
    {
        try {
            $em = $this->tenantManager->getEntityManager();
            
            $activity = new UserActivity();
            $activity->setUser($user);
            $activity->setAction($action);
            $activity->setDetails($details);
            
            if ($request) {
                $activity->setIpAddress($request->getClientIp());
                $activity->setUserAgent($request->headers->get('User-Agent'));
            }
            
            $em->persist($activity);
            $em->flush();
            
            $this->logger->info('User activity logged', [
                'user_id' => $user->getId(),
                'action' => $action,
                'details' => $details
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to log user activity', [
                'user_id' => $user->getId(),
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get weekly activity data for dashboard
     *
     * @return array Weekly activity counts
     */
    public function getWeeklyActivityData(): array
    {
        try {
            $em = $this->tenantManager->getEntityManager();
            
            // Get start and end of current week
            $startOfWeek = new \DateTime('monday this week 00:00:00');
            $endOfWeek = new \DateTime('sunday this week 23:59:59');
            
            // Query activities for this week
            $activities = $em->createQueryBuilder()
                ->select('ua')
                ->from('App\Entity\App\UserActivity', 'ua')
                ->where('ua.activityDate BETWEEN :start AND :end')
                ->andWhere('ua.action IN (:actions)')
                ->setParameter('start', $startOfWeek)
                ->setParameter('end', $endOfWeek)
                ->setParameter('actions', ['login', 'dashboard_view', 'page_view'])
                ->getQuery()
                ->getResult();
            
            // Initialize weekly data
            $weeklyData = [
                'Monday' => 0,
                'Tuesday' => 0,
                'Wednesday' => 0,
                'Thursday' => 0,
                'Friday' => 0,
                'Saturday' => 0,
                'Sunday' => 0
            ];
            
            // Count activities by day
            foreach ($activities as $activity) {
                $dayName = $activity->getActivityDate()->format('l');
                if (isset($weeklyData[$dayName])) {
                    $weeklyData[$dayName]++;
                }
            }
            
            return $weeklyData;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get weekly activity data', [
                'error' => $e->getMessage()
            ]);
            
            // Return fallback data if query fails
            return [
                'Monday' => 0,
                'Tuesday' => 0,
                'Wednesday' => 0,
                'Thursday' => 0,
                'Friday' => 0,
                'Saturday' => 0,
                'Sunday' => 0
            ];
        }
    }

    /**
     * Get monthly activity statistics
     *
     * @return array Monthly activity statistics
     */
    public function getMonthlyActivityStats(): array
    {
        try {
            $em = $this->tenantManager->getEntityManager();
            
            $startOfMonth = new \DateTime('first day of this month 00:00:00');
            $endOfMonth = new \DateTime('last day of this month 23:59:59');
            
            $totalActivities = $em->createQueryBuilder()
                ->select('COUNT(ua.id)')
                ->from('App\Entity\App\UserActivity', 'ua')
                ->where('ua.activityDate BETWEEN :start AND :end')
                ->setParameter('start', $startOfMonth)
                ->setParameter('end', $endOfMonth)
                ->getQuery()
                ->getSingleScalarResult();
            
            $uniqueUsers = $em->createQueryBuilder()
                ->select('COUNT(DISTINCT ua.user)')
                ->from('App\Entity\App\UserActivity', 'ua')
                ->where('ua.activityDate BETWEEN :start AND :end')
                ->setParameter('start', $startOfMonth)
                ->setParameter('end', $endOfMonth)
                ->getQuery()
                ->getSingleScalarResult();
            
            return [
                'total_activities' => $totalActivities,
                'unique_users' => $uniqueUsers,
                'period' => $startOfMonth->format('Y-m')
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get monthly activity stats', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'total_activities' => 0,
                'unique_users' => 0,
                'period' => date('Y-m')
            ];
        }
    }
}
