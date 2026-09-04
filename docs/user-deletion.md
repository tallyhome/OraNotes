# Suppression de comptes (admin)

1. **Désactivation** (`is_active=false`) : connexion refusée, données intactes, sessions révoquées.
2. **Soft delete (admin)** : le compte disparaît des listes actives, login impossible, bureaux/notes conservés.
3. **Restauration (admin)** : réactive le compte.
4. **Suppression profil (utilisateur)** : `forceDelete` + cascade (bureaux, notes, pièces). Irréversible.
5. Transfert d’ownership : changer le propriétaire d’un bureau avant purge via l’admin Bureaux.

Aucun mot de passe n’est journalisé.
