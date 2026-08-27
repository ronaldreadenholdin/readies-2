<?php

namespace App\Http\Controllers;

use App\Models\BobCMessage;
use App\Services\BobGClient;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class BobCController extends Controller
{
    public function index()
    {
        return view('bob_c.index');
    }

    public function status(BobGClient $client)
    {
        return response()->json(['ok' => true] + $client->status() + [
            'history_count' => BobCMessage::query()->count(),
        ]);
    }

    public function history()
    {
        return response()->json([
            'ok' => true,
            'messages' => BobCMessage::query()->oldest()->get(['id', 'role', 'content', 'created_at']),
        ]);
    }

    public function ask(Request $request, BobGClient $client)
    {
        $message = trim((string) $request->input('message', ''));

        try {
            $history = BobCMessage::query()
                ->oldest()
                ->get(['role', 'content'])
                ->toArray();
            $result = $client->ask($message, $history);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }

        BobCMessage::query()->create([
            'user_id' => optional($request->user())->id,
            'role' => 'user',
            'content' => $message,
        ]);
        BobCMessage::query()->create([
            'user_id' => optional($request->user())->id,
            'role' => 'assistant',
            'content' => $result['reply'],
        ]);

        return response()->json($result + [
            'messages' => BobCMessage::query()->oldest()->get(['id', 'role', 'content', 'created_at']),
        ]);
    }

    public function clear()
    {
        BobCMessage::query()->delete();

        return response()->json(['ok' => true, 'messages' => []]);
    }
}
