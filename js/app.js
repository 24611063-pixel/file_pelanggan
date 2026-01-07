// Fungsi untuk sync pending orders
async function syncPendingOrders() {
    const pendingOrders = JSON.parse(localStorage.getItem('pendingOrders') || '[]');
    
    if (pendingOrders.length === 0) {
        console.log('No pending orders to sync');
        return;
    }
    
    try {
        const response = await fetch('sync_pending_orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ orders: pendingOrders })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log(`✅ Synced ${result.synced} orders`);
            
            // Hapus yang sudah sync dari localStorage
            if (result.synced > 0) {
                localStorage.removeItem('pendingOrders');
                alert(`Berhasil sync ${result.synced} order yang pending`);
            }
        } else {
            console.error('Sync failed:', result);
        }
        
    } catch (error) {
        console.error('Sync error:', error);
    }
}

// Panggil sync saat online
window.addEventListener('online', syncPendingOrders);

// Atau manual sync button
function syncOrders() {
    syncPendingOrders();
}