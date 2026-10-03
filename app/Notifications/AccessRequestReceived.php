<?php

namespace App\Notifications;

use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccessRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public AccessRequest $accessRequest)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $accessRequest = $this->accessRequest;

        return (new MailMessage)
            ->subject('New TB-AI access request')
            ->line('A new access request is waiting for review.')
            ->line('Name: '.$accessRequest->name)
            ->line('Role: '.$accessRequest->role)
            ->line('Organisation: '.$accessRequest->organisation)
            ->line('District: '.$accessRequest->district)
            ->line('Email: '.$accessRequest->email);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'access_request_id' => $this->accessRequest->id,
            'name' => $this->accessRequest->name,
            'role' => $this->accessRequest->role,
            'organisation' => $this->accessRequest->organisation,
            'district' => $this->accessRequest->district,
            'email' => $this->accessRequest->email,
        ];
    }
}
