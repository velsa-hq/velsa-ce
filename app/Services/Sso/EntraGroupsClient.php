<?php

namespace App\Services\Sso;

use Illuminate\Support\Facades\Http;

/**
 * Reads group membership from Microsoft Graph via the OIDC access
 * token. Needs GroupMember.Read.All or Directory.Read.All; without
 * them the call 403s and we fall back to no groups so sign-in still
 * succeeds.
 */
class EntraGroupsClient
{
    protected const GRAPH_ME_MEMBER_OF = 'https://graph.microsoft.com/v1.0/me/memberOf';

    /**
     * @return list<string> group GUIDs the user belongs to
     */
    public function fetchGroupIds(string $accessToken): array
    {
        if ($accessToken === '') {
            return [];
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(8)
            ->get(self::GRAPH_ME_MEMBER_OF);

        if (! $response->successful()) {
            // group read failure must not break sign-in
            report(new \RuntimeException(
                "MS Graph /me/memberOf returned {$response->status()}: ".substr($response->body(), 0, 200),
            ));

            return [];
        }

        $ids = [];
        foreach ((array) $response->json('value', []) as $entry) {
            if (($entry['@odata.type'] ?? null) !== '#microsoft.graph.group') {
                continue; // skip directoryRole / orgContact entries
            }
            $id = $entry['id'] ?? null;
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
