# 📡 Endpoints API Backend — Reliance

> Base URL: `/api/v1`  
> Auth: Token **Sanctum** via header `Authorization: Bearer {token}`

---

## 1. 🔐 Auth (`/api/v1/auth`)

### Routes Publiques (sans token)

| Méthode | Endpoint | Rôle | Détail |
|---------|----------|------|--------|
| `POST` | `/auth/register` | Inscription | Crée un compte utilisateur, renvoie un token |
| `POST` | `/auth/login` | Connexion | Vérifie email/mot de passe, renvoie un token Sanctum |
| `POST` | `/auth/forgot-password` | Mot de passe oublié | Envoie un email avec lien/code de réinitialisation |
| `POST` | `/auth/reset-password` | Réinitialiser MDP | Valide le token + nouveau mot de passe |
| `GET` | `/auth/email/verify/{id}/{hash}` | Vérifier email | Lien signé cliqué depuis l'email, marque l'email comme vérifié |

### Routes Authentifiées (`auth:sanctum`)

| Méthode | Endpoint | Rôle | Détail |
|---------|----------|------|--------|
| `GET` | `/auth/me` | Profil courant | Renvoie les infos de l'utilisateur connecté |
| `POST` | `/auth/logout` | Déconnexion | Révoque le token Sanctum actuel |
| `POST` | `/auth/refresh` | Rafraîchir token | Génère un nouveau token |
| `POST` | `/auth/email/verification-notification` | Renvoyer email vérif | Renvoie l'email de vérification si pas encore vérifié |

### 2FA (`auth:sanctum`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `POST` | `/auth/2fa/generate` | Génère un secret TOTP (QR code) |
| `POST` | `/auth/2fa/enable` | Active la 2FA avec code de vérification |
| `POST` | `/auth/2fa/disable` | Désactive la 2FA |

### Profil (`auth:sanctum` + `verified`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `PUT` | `/auth/profile` | Met à jour les infos du profil |
| `POST` | `/auth/profile/avatar` | Upload d'avatar (multipart/form-data) |
| `PUT` | `/auth/profile/password` | Change le mot de passe |

### Upgrade de rôle (`auth:sanctum` + `verified`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `POST` | `/auth/request-upgrade` | Demande de passage Client → Technicien |

### Admin (`role:admin`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/auth/admin/stats` | Dashboard statistiques admin |
| `POST` | `/auth/admin/users/{user}/roles` | Assigner un rôle à un utilisateur |
| `DELETE` | `/auth/admin/users/{user}/roles/{role}` | Révoquer un rôle |
| `GET` | `/auth/admin/users` | Liste tous les utilisateurs |
| `GET` | `/auth/admin/users/{id}` | Détail d'un utilisateur |
| `PATCH` | `/auth/admin/users/{id}/toggle-active` | Activer/désactiver un compte |
| `GET` | `/auth/admin/technicians/pending` | Liste des techniciens en attente d'approbation |
| `POST` | `/auth/admin/technicians/{id}/approve` | Approuver un technicien |
| `POST` | `/auth/admin/technicians/{id}/reject` | Rejeter un technicien |
| `GET` | `/auth/admin/upgrade-requests` | Liste des demandes d'upgrade de rôle |
| `POST` | `/auth/admin/upgrade-requests/{id}/approve` | Approuver une demande d'upgrade |
| `POST` | `/auth/admin/upgrade-requests/{id}/reject` | Rejeter une demande d'upgrade |

---

## 2. 👤 Profile (`/api/v1`)

### Routes Publiques

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/skills` | Liste toutes les compétences disponibles |
| `GET` | `/technicians` | Recherche/liste des techniciens (public) |
| `GET` | `/technicians/{id}/profile` | Profil public d'un technicien |

### Routes Authentifiées — Technicien

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/technician/profile` | Mon profil technicien |
| `GET` | `/technician/stats` | Mes statistiques technicien |
| `PUT` | `/technician/profile` | Mettre à jour mon profil technicien |
| `PUT` | `/technician/location` | Mettre à jour ma localisation |
| `GET` | `/technician/skills` | Mes compétences |
| `POST` | `/technician/skills` | Ajouter une compétence |
| `DELETE` | `/technician/skills/{id}` | Supprimer une compétence |

### Routes Authentifiées — Localisations

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/locations` | Mes adresses enregistrées |
| `POST` | `/locations` | Ajouter une adresse |
| `DELETE` | `/locations/{id}` | Supprimer une adresse |
| `PATCH` | `/locations/{id}/default` | Définir comme adresse par défaut |

### Routes Authentifiées — Agences

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/agencies` | Liste des agences |
| `POST` | `/agencies` | Créer une agence |
| `GET` | `/agencies/{id}` | Détail d'une agence |
| `PUT` | `/agencies/{id}` | Modifier une agence |
| `GET` | `/agencies/{id}/members` | Membres d'une agence |
| `POST` | `/agencies/{id}/members` | Ajouter un membre |
| `DELETE` | `/agencies/{id}/members/{userId}` | Retirer un membre |
| `GET` | `/my-agency` | Mon agence |

---

## 3. 📅 Schedule (`/api/v1/technician`) — `auth:sanctum`

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/technician/calendar` | Données du calendrier du technicien |
| `POST` | `/technician/time-slots` | Créer un créneau horaire |
| `DELETE` | `/technician/time-slots/{id}` | Supprimer un créneau |
| `POST` | `/technician/exceptions` | Créer une exception (congé, indispo) |
| `DELETE` | `/technician/exceptions/{id}` | Supprimer une exception |

---

## 4. 🔗 Matching (`/api/v1`)

### Route Publique

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/proposals/stream` | Flux SSE temps réel pour propositions (auth manuelle via query param) |

### Routes Authentifiées — Client (Demandes)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `POST` | `/requests` | Créer une demande d'intervention |
| `GET` | `/requests` | Lister mes demandes |
| `GET` | `/requests/{id}` | Détail d'une demande |
| `POST` | `/requests/{id}/match` | Lancer le matching (trouver des techniciens) |
| `POST` | `/requests/{id}/select-technician` | Sélectionner un technicien parmi les propositions |

### Routes Authentifiées — Technicien (Propositions)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/proposals/my` | Mes propositions reçues |
| `GET` | `/proposals/check-new` | Vérifier nouvelles propositions (polling) |
| `GET` | `/proposals/active-mission` | Ma mission active en cours |
| `POST` | `/proposals/{id}/accept` | Accepter une proposition |
| `POST` | `/proposals/{id}/refuse` | Refuser une proposition |

### Missions & Position

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/mission/active` | Mission active côté client |
| `POST` | `/position/update` | Technicien envoie sa position GPS |
| `GET` | `/position/technician` | Client récupère la position du technicien |

### Admin Matching (`role:admin`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/admin/requests` | Toutes les demandes (admin) |
| `POST` | `/admin/requests/{id}/assign` | Assigner manuellement un technicien |

---

## 5. ⭐ Gamification (`/api/v1`)

### Routes Publiques

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/reviews/technician/{id}` | Avis reçus par un technicien |
| `GET` | `/badges` | Liste de tous les badges |
| `GET` | `/badges/user/{id}` | Badges d'un utilisateur |

### Routes Authentifiées

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `POST` | `/reviews` | Laisser un avis |
| `GET` | `/reviews/mine` | Mes avis laissés |
| `GET` | `/reputation/{id}` | Score de réputation d'un utilisateur |

---

## 6. 🔧 Operations (`/api/v1`) — `auth:sanctum`

### Devis (Quotes)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `POST` | `/quotes` | Créer un devis |
| `GET` | `/quotes/{id}` | Voir un devis |
| `POST` | `/quotes/{id}/send` | Envoyer le devis au client |
| `POST` | `/quotes/{id}/accept` | Client accepte le devis |
| `POST` | `/quotes/{id}/reject` | Client refuse le devis |
| `GET` | `/requests/{requestId}/quotes` | Devis liés à une demande |

### Factures (Invoices)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/invoices` | Lister mes factures |
| `GET` | `/invoices/{id}` | Détail d'une facture |
| `POST` | `/invoices/{id}/pay` | Marquer facture comme payée |

### Rendez-vous (Appointments)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/appointments` | Mes rendez-vous |
| `GET` | `/appointments/upcoming` | Prochains rendez-vous |
| `POST` | `/appointments` | Créer un rendez-vous |
| `GET` | `/appointments/{id}` | Détail d'un rendez-vous |
| `PATCH` | `/appointments/{id}/status` | Changer le statut |

### Jobs (Suivi d'intervention)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/jobs` | Lister mes jobs |
| `POST` | `/jobs` | Créer un job |
| `GET` | `/jobs/{id}` | Détail d'un job |
| `PATCH` | `/jobs/{id}/status` | Changer le statut du job |
| `PUT` | `/jobs/{id}/phases` | Mettre à jour les phases |
| `GET` | `/jobs/{id}/tracking` | Suivi en temps réel du job |

### Interventions

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/interventions` | Liste des interventions |
| `POST` | `/interventions` | Créer une intervention |
| `GET` | `/interventions/{id}` | Détail |
| `POST` | `/interventions/{id}/start` | Démarrer l'intervention |
| `POST` | `/interventions/{id}/complete` | Terminer l'intervention |

---

## 7. 💬 Social (`/api/v1`)

### Routes Publiques

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/posts/explore` | Explorer les posts (flux public) |
| `GET` | `/posts/search` | Recherche de posts |
| `GET` | `/posts/{id}` | Voir un post |
| `GET` | `/posts/{postId}/comments` | Commentaires d'un post |
| `GET` | `/posts/{postId}/likers` | Qui a liké un post |
| `GET` | `/users/{userId}/posts` | Posts d'un utilisateur |
| `GET` | `/users/{userId}/followers` | Followers d'un utilisateur |
| `GET` | `/users/{userId}/following` | Abonnements d'un utilisateur |
| `GET` | `/users/{userId}/follow-counts` | Compteurs follow/followers |

### Posts (`auth:sanctum`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/posts/feed` | Mon fil d'actualité (abonnements) |
| `GET` | `/posts/trending` | Posts tendance |
| `POST` | `/posts` | Créer un post |
| `PUT` | `/posts/{id}` | Modifier un post |
| `DELETE` | `/posts/{id}` | Supprimer un post |

### Commentaires, Likes, Follows (`auth:sanctum`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `POST` | `/posts/{postId}/comments` | Commenter |
| `PUT` | `/posts/{postId}/comments/{commentId}` | Modifier un commentaire |
| `DELETE` | `/posts/{postId}/comments/{commentId}` | Supprimer un commentaire |
| `POST` | `/posts/{postId}/like` | Toggle like/unlike |
| `POST` | `/follows/{userId}` | Suivre un utilisateur |
| `DELETE` | `/follows/{userId}` | Ne plus suivre |
| `GET` | `/follows/{userId}/check` | Vérifie si je suis abonné |
| `GET` | `/follows/suggestions` | Suggestions d'utilisateurs à suivre |

### Messagerie (`auth:sanctum`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/conversations` | Mes conversations |
| `POST` | `/conversations` | Créer une conversation |
| `GET` | `/conversations/unread-count` | Nombre de messages non lus |
| `GET` | `/conversations/{id}` | Détail d'une conversation |
| `GET` | `/conversations/{id}/messages` | Messages d'une conversation |
| `POST` | `/conversations/{id}/messages` | Envoyer un message |
| `POST` | `/conversations/{id}/messages/read` | Marquer messages comme lus |
| `GET` | `/conversations/{id}/messages/attachments` | Pièces jointes |
| `DELETE` | `/conversations/{id}/messages/{messageId}` | Supprimer un message |

### Admin Conversations (`role:admin`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/admin/conversations` | Toutes les conversations |
| `GET` | `/admin/conversations/search` | Recherche dans les conversations |
| `GET` | `/admin/conversations/{id}` | Détail (supervision) |

---

## 8. 🏛️ Governance (`/api/v1`) — `auth:sanctum`

### SLA Events (utilisateur)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `POST` | `/sla-events` | Créer un événement SLA |
| `GET` | `/sla-events/mine` | Mes événements SLA |
| `GET` | `/sla-events/request/{requestId}` | Événements SLA d'une demande |

### Litiges (Disputes)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `POST` | `/disputes` | Ouvrir un litige |
| `GET` | `/disputes` | Mes litiges |
| `GET` | `/disputes/{id}` | Détail d'un litige |
| `POST` | `/disputes/{id}/message` | Ajouter un message au litige |

### Analytics (utilisateur)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `POST` | `/analytics/track` | Tracker un événement analytics |

### Admin Governance (`role:admin`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/sla-rules` | Liste des règles SLA |
| `POST` | `/sla-rules` | Créer une règle SLA |
| `GET` | `/sla-rules/{id}` | Détail d'une règle |
| `PUT` | `/sla-rules/{id}` | Modifier une règle |
| `PATCH` | `/sla-rules/{id}/toggle` | Activer/désactiver une règle |
| `GET` | `/audit-logs` | Logs d'audit |
| `GET` | `/audit-logs/{id}` | Détail d'un log |
| `GET` | `/analytics` | Données analytics |
| `GET` | `/analytics/summary` | Résumé analytics |
| `GET` | `/admin/disputes` | Tous les litiges |
| `POST` | `/admin/disputes/{id}/resolve` | Résoudre un litige |

---

## 9. 💰 Finance (`/api/v1`)

### Webhook Public (sans auth, vérifié par signature)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `POST` | `/webhooks/notchpay` | Callback NotchPay (paiement confirmé/échoué) |

### Wallet (`auth:sanctum`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/wallet` | Voir mon portefeuille (solde) |
| `POST` | `/wallet/recharge` | Recharger le portefeuille (initie paiement NotchPay) |
| `GET` | `/wallet/transactions` | Historique de transactions |
| `POST` | `/wallet/withdraw` | Demander un retrait |

### Paiements

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/payments` | Lister mes paiements |
| `GET` | `/payments/{id}` | Détail d'un paiement |
| `POST` | `/payments/{id}/confirm` | Confirmer un paiement |

### Holds (Escrow / Séquestre)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/holds` | Mes fonds bloqués |
| `POST` | `/holds` | Créer un blocage de fonds |
| `POST` | `/holds/{id}/capture` | Capturer les fonds (libérer au technicien) |
| `POST` | `/holds/{id}/release` | Relâcher les fonds (rembourser le client) |

### Abonnements

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/subscriptions/plans` | Voir les plans d'abonnement |
| `GET` | `/subscriptions/mine` | Mon abonnement actuel |
| `POST` | `/subscriptions/subscribe` | S'abonner à un plan |
| `POST` | `/subscriptions/cancel` | Annuler l'abonnement |
| `POST` | `/subscriptions/pause` | Mettre en pause |
| `POST` | `/subscriptions/resume` | Reprendre l'abonnement |

### Crédits

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/credits/rates` | Taux de change des crédits |
| `POST` | `/credits/purchase` | Acheter des crédits |

### Admin Finance (`role:admin`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/admin/subscriptions` | Tous les abonnements |
| `GET` | `/admin/subscriptions/stats` | Statistiques abonnements |
| `PUT` | `/admin/subscriptions/{id}` | Modifier un abonnement |
| `POST` | `/admin/subscriptions/plans` | Créer un plan |
| `PUT` | `/admin/subscriptions/plans/{id}` | Modifier un plan |
| `GET` | `/admin/fees` | Frais de plateforme |
| `POST` | `/admin/fees` | Créer un frais |
| `PUT` | `/admin/fees/{id}` | Modifier un frais |
| `PATCH` | `/admin/fees/{id}/toggle` | Activer/désactiver un frais |
| `GET` | `/admin/credits/rates` | Taux de change (admin) |
| `POST` | `/admin/credits/rates` | Définir un taux |
| `GET` | `/admin/payouts` | Tous les retraits |
| `GET` | `/admin/payouts/pending` | Retraits en attente |
| `GET` | `/admin/payouts/stats` | Stats retraits |
| `POST` | `/admin/payouts/{id}/approve` | Approuver un retrait |
| `POST` | `/admin/payouts/{id}/reject` | Rejeter un retrait |

---

## 10. 🔔 Notification (`/api/v1`) — `auth:sanctum`

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/notifications` | Mes notifications |
| `POST` | `/notifications/{id}/read` | Marquer comme lue |
| `POST` | `/notifications/read-all` | Tout marquer comme lu |
| `GET` | `/notifications/unread-count` | Nombre de non-lues |
| `POST` | `/push-tokens` | Enregistrer un token push (mobile) |
| `DELETE` | `/push-tokens/{id}` | Supprimer un token push |
| `GET` | `/notification-settings` | Mes préférences de notification |
| `PUT` | `/notification-settings` | Modifier mes préférences |

---

## 11. 📄 KYC (`/api/v1/auth`) — `auth:sanctum`

> ⚠️ Ces routes sont imbriquées dans le groupe Auth (incluses via `require`)

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `GET` | `/auth/kyc/mine` | Mes documents KYC |
| `POST` | `/auth/kyc/upload` | Upload d'un document KYC (multipart) |
| `GET` | `/auth/kyc/admin/documents` | Tous les documents KYC (admin) |
| `POST` | `/auth/kyc/{id}/validate` | Valider/rejeter un document (admin) |

---

## 12. 📩 Contact (`/api/v1`) — Public

| Méthode | Endpoint | Rôle |
|---------|----------|------|
| `POST` | `/contact` | Envoyer un message via le formulaire de contact |

---

## 📡 Canaux WebSocket (Pusher/Reverb)

| Canal | Accès | Usage |
|-------|-------|-------|
| `conversation.{conversationId}` | Membres de la conversation | Messages temps réel |
| `proposals.{userId}` | L'utilisateur concerné | Nouvelles propositions / mises à jour |
| `jobs.{jobId}` | Technicien ou client du job | Suivi d'intervention en direct |
| `tracking.{jobId}` | Technicien ou client du job | Géolocalisation temps réel |
| `wallet.{userId}` | L'utilisateur concerné | Mises à jour solde/paiements |
| `notifications.{userId}` | L'utilisateur concerné | Notifications push temps réel |
| `quotes.{userId}` | L'utilisateur concerné | Devis/factures temps réel |
| `disputes.{disputeId}` | Parties au litige | Messages du litige |
| `user.{userId}` | L'utilisateur concerné | KYC, upgrades de rôle |
| `admin.dashboard` | Admins uniquement | Événements admin temps réel |

---

## 🔄 Flux type d'une requête API

```
Client (Frontend/Mobile)
    │
    ├── Header: Authorization: Bearer {sanctum_token}
    ├── Header: Content-Type: application/json
    ├── Header: Accept: application/json
    │
    ▼
Laravel API (api/v1/...)
    │
    ├── 1. Middleware auth:sanctum  →  vérifie le token
    ├── 2. Middleware verified      →  vérifie email confirmé
    ├── 3. Middleware role:admin    →  vérifie le rôle (si admin)
    ├── 4. Middleware permission:X  →  vérifie une permission spécifique
    │
    ▼
Controller → Service → Model → DB
    │
    ▼
Response JSON { data, message, status }
    │
    ├── Optionnel: Broadcast Event (WebSocket)
    └── Optionnel: Notification (email, push, DB)
```

---

## 📊 Résumé

| Module | Endpoints | Auth requise |
|--------|-----------|-------------|
| Auth | ~25 | Mixte (public + auth + admin) |
| Profile | ~20 | Mixte (public + auth) |
| Schedule | 5 | Auth |
| Matching | ~14 | Mixte (SSE public + auth + admin) |
| Gamification | 6 | Mixte |
| Operations | ~20 | Auth |
| Social | ~28 | Mixte (public + auth + admin) |
| Governance | ~16 | Auth + admin |
| Finance | ~28 | Mixte (webhook + auth + admin) |
| Notification | 8 | Auth |
| KYC | 4 | Auth + admin |
| Contact | 1 | Public |
| **Total** | **~175** | — |
