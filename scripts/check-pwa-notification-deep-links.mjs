import fs from 'node:fs';
const read = (path) => fs.readFileSync(path, 'utf8');
const link = read('app/Support/Notifications/NotificationDeepLink.php');
const payload = read('app/Support/Notifications/NotificationPayload.php');
const controller = read('app/Http/Controllers/NotificationController.php');
const push = read('app/Http/Controllers/AdminPushNotificationController.php');
const oneSignal = read('app/Services/OneSignalService.php');
const routes = read('routes/web.php');
const checks = [
    ['deep link uses a versioned internal notification identity', link.includes("'deep_link_version'") && link.includes("'notification_id'")],
    ['deep link targets one canonical same-origin route', link.includes("route('notifications.open'") && payload.includes('safeWebUrl')],
    ['open route is numeric and throttled', routes.includes("whereNumber('notification')") && routes.includes("middleware('throttle:120,1')")],
    ['open action resolves the authenticated recipient', controller.includes('notificationFor($recipient, $notification)')],
    ['open action marks only the visible record as read', controller.includes('markAsReadByRecipient($recipient)')],
    ['Inbox is created before Push is queued', push.indexOf('NotificationService::send') < push.indexOf('SendOneSignalNotification::dispatch')],
    ['Push and Inbox share the same deep-link payload', push.includes('NotificationDeepLink::payload($notification)') && push.includes('$deepLink,')],
    ['OneSignal receives only a normalized same-origin web URL', oneSignal.includes('NotificationPayload::normalize($data)') && oneSignal.includes('NotificationPayload::safeWebUrl($data)')],
];
for (const [label, passed] of checks) {
    if (!passed) throw new Error(`FAIL: ${label}`);
    console.log(`PASS: ${label}`);
}
console.log(`PWA notification deep-link contract passed (${checks.length} checks).`);
