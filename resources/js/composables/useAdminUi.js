const ACTIVITY_LABELS = {
    login: 'Connexion',
    'note.created': 'Note créée',
    'note.updated': 'Note mise à jour',
    'note.deleted': 'Note supprimée',
    'note.restored': 'Note restaurée',
    'note.force_deleted': 'Note purgée',
    'share.added': 'Partage ajouté',
    'share.removed': 'Partage retiré',
    'workspace.created': 'Bureau créé',
    'workspace.deleted': 'Bureau supprimé',
    'workspace.updated': 'Bureau mis à jour',
    'workspace.restored': 'Bureau restauré',
    'workspace.force_deleted': 'Bureau purgé',
    'workspace.locked': 'Bureau verrouillé',
    'workspace.unlocked': 'Bureau déverrouillé',
    'user.created': 'Utilisateur créé',
    'user.updated': 'Utilisateur mis à jour',
    'user.deleted': 'Utilisateur supprimé',
    'user.disabled': 'Utilisateur désactivé',
    'user.enabled': 'Utilisateur activé',
    'user.role_changed': 'Rôle modifié',
    'app.updated': 'Application mise à jour',
    'app.rolled_back': 'Application restaurée',
    'setting.changed': 'Réglage modifié',
};

export function paginatorRows(value) {
    if (Array.isArray(value?.data)) {
        return value.data;
    }

    return [];
}

export function formatDateTime(iso) {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone: 'Europe/Paris',
    }).format(date);
}

export function formatBytes(bytes) {
    const size = Number(bytes) || 0;
    if (size < 1024) {
        return `${size} o`;
    }
    if (size < 1024 * 1024) {
        return `${Math.round(size / 1024)} Kio`;
    }
    if (size < 1024 * 1024 * 1024) {
        return `${(size / (1024 * 1024)).toFixed(1)} Mio`;
    }

    return `${(size / (1024 * 1024 * 1024)).toFixed(2)} Gio`;
}

export function activityLabel(action) {
    return ACTIVITY_LABELS[action] || action || 'Action';
}

export function roleLabel(role) {
    return role === 'admin' ? 'Administrateur' : 'Utilisateur';
}
