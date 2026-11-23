<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $message;

    public int|string $groupId;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>|int|string  $messageOrGroupId
     * @param  array<string, mixed>|int|string|null  $groupIdOrMessage
     */
    public function __construct(array|int|string $messageOrGroupId = [], array|int|string|null $groupIdOrMessage = null)
    {
        if (is_array($messageOrGroupId)) {
            $this->message = $messageOrGroupId;
            $this->groupId = $groupIdOrMessage ?? ($messageOrGroupId['group_id'] ?? '');
        } else {
            $this->groupId = $messageOrGroupId;
            $this->message = is_array($groupIdOrMessage) ? $groupIdOrMessage : [];
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\Channel('group.' . $this->groupId),
            new \Illuminate\Broadcasting\Channel('sidebar.updates'),
        ];
    }
}
