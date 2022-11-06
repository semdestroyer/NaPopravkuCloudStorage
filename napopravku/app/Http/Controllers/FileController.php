<?php

namespace App\Http\Controllers;


use App\Models\FileCleaning;
use App\Models\FileUrl;
use Carbon\Traits\Date;
use Faker\Core\DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use function PHPUnit\Framework\isEmpty;

class FileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['getPublicFile']]);
    }


    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'directory' => 'string',
            'delay' => 'date'
        ]);
        $path = '';
        if ($request->file('file')->getSize() >= 20 * pow(2, 20)) {
            return response()->json([
                'result' => 'File larger than 20mb'
            ], 400);
        }
        if ($request->file('file')->extension() == "php") {
            return response()->json([
                'result' => 'Php extension is not allowed'
            ], 400);
        }
        if ($this->userFilesSize() >= 100 * pow(2, 20)) {
            return response()->json([
                'result' => 'User file limit exceeded'
            ], 400);
        }
        $path = Auth::user()->id . '/' . $request->file('file')->getClientOriginalName();
        if (!empty($validator->validated()['directory'])) {
            $path = Auth::user()->id . '/' . $validator->validated()['directory'] . '/' . $request->file('file')->getClientOriginalName();
        }
        if (!empty($validator->validated()['delay'])) {
            $fileClean = new FileCleaning();
            $fileClean->path = $path;
            $fileClean->deleteAt = date('Y-m-d H:i:s', strtotime($validator->validated()['delay']));
            $fileClean->save();
        }
        Storage::disk('local')->put($path, $request->file('file')->getContent());
        return response()->json([
            'result' => 'file successfully uploaded'
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function createDirectory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'directory' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => $validator->errors()->toJson()
            ], 400);
        }

        if (str_contains($validator->validated()['directory'], '/')) {
            return response()->json([
                'result' => 'subdirectory not allowed'
            ], 400);
        }
        $path = realpath($validator->validated()['directory']);
        Storage::disk('local')->makeDirectory($path);
        return response()->json([
            'result' => 'directory successfully uploaded'
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse|BinaryFileResponse
     * @throws ValidationException
     */
    public function download(Request $request): JsonResponse|BinaryFileResponse
    {
        $validator = Validator::make($request->all(), [
            'directory' => 'string',
            'file' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        if ($this->isFileRelevant(Auth::user()->id . '/' . $validator->validated()['file'])) {
            return response()->download(
                Storage::disk('local')->path(Auth::user()->id . '/' . $validator->validated()['file'])
            );
        }
        return response()->json([
            'result' => 'file not exist'
            ]
        );
    }


    /**
     * Display the specified resource.
     *
     */
    public function showUserFiles(): JsonResponse
    {
        $result = [];
        $files = Storage::disk('local')->allFiles(Auth::user()->id);
        foreach ($files as $file) {
            array_push($result, substr($file, strpos($file, '/', 1)));
        }
        return response()->json(['files' => $result]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     */
    public function rename(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|string',
            'new_file' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        if (!Storage::disk('local')->exists(Auth::user()->id . '/' . $validator->validated()['file'])) {
            return response()->json([
                "result" => "file not exists"
            ], 400);
        }

        Storage::disk('local')->move(Auth::user()->id . '/' . $validator->validated()['file'],
            Auth::user()->id . '/' . $validator->validated()['new_file']);
        return response()->json(['result' => 'file successfully renamed']);
    }

    /**
     */
    public function delete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|string',
            'directory' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        if (!Storage::disk('local')->exists(Auth::user()->id . '/' . $validator->validated()['file'])) {
            return response()->json(["result" => "file not exists"]);
        }

        Storage::disk('local')->delete(Auth::user()->id . '/' . $validator->validated()['file']);
        return response()->json(['result' => 'file successfully delete']);
    }

    /**
     * @param Request $request
     * @return bool
     * @throws ValidationException
     */
    public function generateFilePublicLink(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|string',
        ]);


        $fileUrl = new FileUrl();
        $fileUrl->path = Auth::user()->id . '/' . $validator->validated()['file'];
        $fileUrl->url = Hash::make($validator->validated()['file']);
        $fileUrl->save();
        return response()->json([
            'result' => $fileUrl->url
        ]);
    }

    function getPublicFile($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|string',
        ]);

        $file = FileUrl::where('url', $id)->first();
        if(empty($file))
        {
            return response()->json([
                'result' => 'file not exist'
            ], 400);
        }
        if(!Storage::disk('local')->exists($file->path))
        {
            return response()->json([
                'result' => 'file not exist'
            ], 400);
        }

        if ($this->isFileRelevant(Auth::user()->id . '/' . $validator->validated()['file'])) {
        return response()->download(
            Storage::disk('local')->path($file->path)
        );
        }


    }

    /**
     * @return JsonResponse
     */
    public function getUserFilesSize(): JsonResponse
    {
        return response()->json([
            'size' => $this->userFilesSize()
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserFilesSizeInsideDirectory(Request $request): JsonResponse
    {
        $result = 0;
        $files = Storage::disk('local')->allFiles(Auth::user()->id . '/' . $request->input('directory'));
        foreach ($files as $file) {
            $result += Storage::disk('local')->size($file);
        }
        return response()->json([
            'size' => $result
        ]);
    }

    /**
     * @return int
     */
    private function userFilesSize(): int
    {
        $result = 0;
        $files = Storage::disk('local')->allFiles(Auth::user()->id);
        foreach ($files as $file) {
            $result += Storage::disk('local')->size($file);
        }
        return $result;

    }

    /**
     * @param string $filename
     * @return bool
     */
    private function isFileRelevant(string $filename): bool
    {
        $file = FileCleaning::where('path', $filename)->first();
        if (empty($file)) {
            return false;
        }
        if(strtotime('now') >= strtotime($file->deleteAt))
        {

            return false;
        }

        return true;
    }

}
