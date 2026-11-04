<?php

namespace App\Http\Controllers;

use App\Domain\Tickets\Models\TicketAttachment;
use App\Domain\Tickets\Services\TicketService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function __invoke(TicketAttachment $attachment): StreamedResponse
    {
        $ticket = $attachment->message->ticket;

        $this->authorize('download', $ticket);

        $disk = Storage::disk(TicketService::DISK);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name);
    }
}
