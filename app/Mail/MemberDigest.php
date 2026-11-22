<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/** The weekly email of a member: the news from what they follow, and a link that switches the email off. */
class MemberDigest extends Mailable
{
    use SerializesModels;

    public string $site;

    public string $off;

    /** @param Collection<int, \App\Models\MemberNotification> $items */
    public function __construct(public User $user, public Collection $items)
    {
        $this->site = (string) \App\Models\Setting::get('site_name', config('app.name'));
        $this->off = URL::signedRoute('account.digest.off', ['user' => $user->id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('account.digest_mail_subject', ['site' => $this->site]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.member-digest');
    }
}
