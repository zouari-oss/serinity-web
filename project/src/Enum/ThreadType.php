<?php

namespace App\Enum;

enum ThreadType: string
{
    case DISCUSSION = 'discussion';
    case QUESTION = 'question';
    case ANNOUNCEMENT = 'announcement';
}
