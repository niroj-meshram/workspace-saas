<x-mail::message>
# {{ $inviterName }} invited you to {{ $workspaceName }}

You've been invited to join the **{{ $workspaceName }}** workspace as {{ $roleLabel }}.

<x-mail::button :url="$acceptUrl">
Accept invitation
</x-mail::button>

**You'll need an account to accept.** If you don't have one yet, the link takes
you to a sign-up form — create your account with **{{ $email }}**, the address
this invitation was sent to, and you'll join {{ $workspaceName }} straight
away. Already have an account on that address? Just sign in.

Signing up with a different address won't work: invitations are tied to the
address they were sent to.

This invitation expires on {{ $expiresAt->toFormattedDayDateString() }}.

If you weren't expecting it, you can ignore this email — nothing happens until
you accept.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
