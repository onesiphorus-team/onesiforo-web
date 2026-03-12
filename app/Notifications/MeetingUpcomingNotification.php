<?php

namespace App\Notifications;

use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use Illuminate\Notifications\Notification;

class MeetingUpcomingNotification extends Notification
{
    public function __construct(
        public MeetingInstance $meetingInstance,
        public OnesiBox $onesiBox,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'meeting_instance_id' => $this->meetingInstance->id,
            'onesi_box_id' => $this->onesiBox->id,
            'message' => 'Meeting upcoming',
            'type' => 'meeting_upcoming',
        ];
    }
}
