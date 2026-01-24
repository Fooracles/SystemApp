// Service Worker for background notifications
self.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'SHOW_NOTIFICATION') {
        const { title, body, icon, tag } = event.data;
        
        self.registration.showNotification(title, {
            body: body,
            icon: icon || '/assets/images/logo.png',
            tag: tag,
            requireInteraction: true,
            actions: [
                {
                    action: 'view',
                    title: 'View Note',
                    icon: '/assets/images/view-icon.png'
                },
                {
                    action: 'dismiss',
                    title: 'Dismiss',
                    icon: '/assets/images/dismiss-icon.png'
                }
            ]
        });
    }
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    
    if (event.action === 'view') {
        // Open the notes page
        event.waitUntil(
            clients.openWindow('/pages/my_notes.php')
        );
    }
});
