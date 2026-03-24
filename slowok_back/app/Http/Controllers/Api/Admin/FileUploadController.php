<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    private const MIME_RULES = [
        'video' => ['mimes:mp4,webm', 'max:51200'],
        'image' => ['mimes:jpeg,png,gif,webp', 'max:5120'],
        'audio' => ['mimes:mp3,wav,ogg', 'max:10240'],
        'thumbnail' => ['mimes:jpeg,png,webp', 'max:2048'],
    ];

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file',
            'type' => 'required|in:video,image,audio,thumbnail',
        ]);

        $type = $request->input('type');
        $rules = self::MIME_RULES[$type] ?? [];
        $request->validate(['file' => $rules]);

        $file = $request->file('file');
        $folder = $type . 's';
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'public');

        return response()->json([
            'success' => true,
            'data' => [
                'url' => '/storage/' . $path,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ],
        ]);
    }

    public function delete(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string']);

        $path = $request->input('path');

        // path traversal 방어: ../ 포함 또는 허용 폴더 외 경로 차단
        if (str_contains($path, '..') || !preg_match('/^(videos|images|audios|thumbnails)\//', $path)) {
            return response()->json(['success' => false, 'message' => '잘못된 파일 경로입니다.'], 400);
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['success' => true, 'message' => '파일이 삭제되었습니다.']);
    }
}
