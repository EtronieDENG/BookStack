<?php

namespace BookStack\Actions;

use BookStack\Auth\Permissions\PermissionService;
use BookStack\Entities\Models\Entity;
use BookStack\Interfaces\Loggable;
use Illuminate\Support\Facades\Log;

class ActivityLogger
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Add a generic activity event to the database.
     *
     * @param string|Loggable $detail
     */
    public function add(string $type, $detail = '')
    {
        $detailToStore = ($detail instanceof Loggable) ? $detail->logDescriptor() : $detail;

        $activity = $this->newActivityForUser($type);
        $activity->detail = $detailToStore;

        if ($detail instanceof Entity) {
            $activity->entity_id = $detail->id;
            $activity->entity_type = $detail->getMorphClass();
        }

        $activity->save();
        $this->setNotification($type);
    }

    /**
     * Get a new activity instance for the current user.
     */
    protected function newActivityForUser(string $type): Activity
    {
        $ip = request()->ip() ?? '';

        return (new Activity())->forceFill([
            'type'     => strtolower($type),
            'user_id'  => user()->id,
            'ip'       => config('app.env') === 'demo' ? '127.0.0.1' : $ip,
        ]);
    }

    /**
     * Removes the entity attachment from each of its activities
     * and instead uses the 'extra' field with the entities name.
     * Used when an entity is deleted.
     */
    public function removeEntity(Entity $entity)
    {
        $entity->activity()->update([
            'detail'       => $entity->name,
            'entity_id'    => null,
            'entity_type'  => null,
        ]);
    }

    /**
     * Flashes a notification message to the session if an appropriate message is available.
     */
    protected function setNotification(string $type)
    {
        $notificationTextKey = 'activities.' . $type . '_notification';
        if (trans()->has($notificationTextKey)) {
            $message = trans($notificationTextKey);
            session()->flash('success', $message);
        }
    }

    /**
     * Log out a failed login attempt, Providing the given username
     * as part of the message if the '%u' string is used.
     */
    public function logFailedLogin(string $username)
    {
        $message = config('logging.failed_login.message');
        if (!$message) {
            return;
        }

        $message = str_replace('%u', $username, $message);
        $channel = config('logging.failed_login.channel');
        Log::channel($channel)->warning($message);
    }
}
