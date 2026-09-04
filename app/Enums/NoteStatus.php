<?php

namespace App\Enums;

enum NoteStatus: string
{
    case Idea = 'idea';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Archived = 'archived';
}
