<?php
/**
 * Email translations - French (Français)
 */

return [
    // Order confirmation email
    'email.order.subject' => 'Confirmation de commande #{order_id} — NicheHome.ch',
    'email.order.title' => 'Merci pour votre commande !',
    'email.order.greeting' => 'Cher/Chère {customer_name},',
    'email.order.intro' => 'Nous avons reçu votre commande et elle est en cours de traitement. Merci de faire vos achats chez NicheHome.ch !',
    'email.order.orderDetails' => 'Détails de la commande',
    'email.order.orderNumber' => 'Numéro de commande',
    'email.order.orderDate' => 'Date de commande',
    'email.order.orderStatus' => 'Statut de commande',
    'email.order.paymentStatus' => 'Statut de paiement',
    'email.order.items' => 'Articles',
    'email.order.product' => 'Produit',
    'email.order.sku' => 'SKU',
    'email.order.qty' => 'Qté',
    'email.order.price' => 'Prix',
    'email.order.total' => 'Total',
    'email.order.subtotal' => 'Sous-total',
    'email.order.shipping' => 'Livraison',
    'email.order.grandTotal' => 'Total général',
    'email.order.paymentMethod' => 'Méthode de paiement',
    'email.order.deliveryMethod' => 'Méthode de livraison',
    'email.order.pickupBranch' => 'Lieu de retrait',
    'email.order.shippingAddress' => 'Adresse de livraison',
    'email.order.branch' => 'Succursale',
    'email.order.address' => 'Adresse',
    'email.order.street' => 'Rue',
    'email.order.city' => 'Ville',
    'email.order.country' => 'Pays',
    'email.order.footer.thanks' => 'Merci de faire vos achats chez NicheHome.ch !',
    'email.order.footer.questions' => 'Si vous avez des questions, veuillez nous contacter à support@nichehome.ch',
    'email.footer.auto' => 'Ceci est un message automatique. Veuillez ne pas répondre à cet e-mail.',
    
    // Payment methods
    'email.payment.cash' => 'Espèces (au retrait)',
    'email.payment.twint' => 'TWINT',
    'email.payment.card' => 'Carte de crédit/débit',
    'email.payment.paypal' => 'PayPal',
    
    // Delivery methods
    'email.delivery.delivery' => 'Livraison',
    'email.delivery.pickup' => 'Retrait',
    'email.delivery.free' => 'GRATUIT',
    'email.delivery.pickupInBranch' => 'Retrait en succursale',
    
    // Order statuses
    'email.status.pending' => 'En attente',
    'email.status.paid' => 'Payé',
    'email.status.awaiting_payment' => 'En attente de paiement',
    'email.status.awaiting_cash_pickup' => 'En attente de paiement en espèces au retrait',
    'email.status.completed' => 'Terminé',
    'email.status.fulfilled' => 'Réalisé',
    'email.status.pending_payment' => 'Paiement en attente',
    'email.status.cash_on_pickup' => 'Espèces au retrait',
    
    // Support auto-reply email
    'email.support.subject' => 'Nous avons reçu votre demande — NicheHome.ch',
    'email.support.title' => 'Nous avons reçu votre demande',
    'email.support.greeting' => 'Cher/Chère {name},',
    'email.support.intro' => 'Merci d\'avoir contacté le support NicheHome.ch. Nous avons reçu votre demande et y répondrons dès que possible.',
    'email.support.yourRequest' => 'Votre demande',
    'email.support.subject_label' => 'Sujet',
    'email.support.message_label' => 'Message',
    'email.support.responseTime' => 'Notre équipe de support répond généralement dans les 24 à 48 heures pendant les jours ouvrables.',
    'email.support.footer.thanks' => 'Merci d\'avoir choisi NicheHome.ch',
    'email.support.footer.urgent' => 'Pour les questions urgentes, veuillez nous appeler directement',
    
    // Admin notification (kept in English)
    'email.admin.order.subject' => 'New Order #{order_id} — NicheHome.ch',
    'email.admin.order.title' => 'New Order Received',
    'email.admin.support.subject' => 'New Support Request — {name}',
    'email.admin.support.title' => 'New Support Request',
];
