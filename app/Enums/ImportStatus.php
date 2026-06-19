<?php

namespace App\Enums;

/** Import job state in the upload -> map -> preview -> commit pipeline; persisted so a job can resume. */
enum ImportStatus: string
{
    case Pending = 'pending';       // file uploaded, awaiting column mapping
    case Previewed = 'previewed';   // dry run complete, ready to commit
    case Completed = 'completed';   // committed (may include skipped error rows)
    case Failed = 'failed';         // commit aborted before finishing
    case Reversed = 'reversed';     // committed then reversed within the window

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting mapping',
            self::Previewed => 'Previewed',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Reversed => 'Reversed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Reversed], true);
    }
}
