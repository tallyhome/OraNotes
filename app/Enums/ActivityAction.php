<?php

namespace App\Enums;

enum ActivityAction: string
{
    case Login = 'login';
    case NoteCreated = 'note.created';
    case NoteUpdated = 'note.updated';
    case NoteDeleted = 'note.deleted';
    case NoteRestored = 'note.restored';
    case NoteForceDeleted = 'note.force_deleted';
    case ShareAdded = 'share.added';
    case ShareRemoved = 'share.removed';
    case WorkspaceCreated = 'workspace.created';
    case WorkspaceDeleted = 'workspace.deleted';
    case WorkspaceUpdated = 'workspace.updated';
    case WorkspaceRestored = 'workspace.restored';
    case WorkspaceForceDeleted = 'workspace.force_deleted';
    case WorkspaceLocked = 'workspace.locked';
    case WorkspaceUnlocked = 'workspace.unlocked';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';
    case UserDisabled = 'user.disabled';
    case UserEnabled = 'user.enabled';
    case UserRoleChanged = 'user.role_changed';
    case AppUpdated = 'app.updated';
    case AppRolledBack = 'app.rolled_back';
    case SettingChanged = 'setting.changed';
}
