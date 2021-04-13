<?php

namespace Musonza\Chat\Models;

use Musonza\Chat\BaseModel;
use Musonza\Chat\ConfigurationManager;

class ConversationDeleted extends BaseModel
{
    protected $table = ConfigurationManager::CONVERSATIONS_TABLE . '_deleted';

    protected $fillable = [
        'conversation_id',
        'participation_id'
    ];

    public function participant()
    {
        return $this->belongsTo(Participation::class, 'participation_id');
    }
}
