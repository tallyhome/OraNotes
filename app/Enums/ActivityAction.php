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
}
