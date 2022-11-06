<?php

namespace App\Http\Controllers;

use App\Models\FileCleaning;
use App\Models\FileUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['getPublicFile']]);
    }


    /**
     * Функция для загрузки файла
     *
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
     * Функция для создания директории
     *
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
     * Функция для скачивания своих файлов
     *
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
     * Функция для просмотра всех файлов на диске
     *
     * @return JsonResponse
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
     * Функция для переименования файлов
     *
     * @param Request $request
     * @return JsonResponse
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
     * Функция для удаления файлов
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
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
     * Функция для генерации публичных ссылок на файл
     *
     * @param Request $request
     * @return JsonResponse
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

    /**
     * Функция для получения публичного файла по хэшу
     *
     * @param $id
     * @param Request $request
     * @return JsonResponse|BinaryFileResponse
     * @throws ValidationException
     */
    function getPublicFile($id, Request $request): JsonResponse|BinaryFileResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|string',
        ]);

        $file = FileUrl::where('url', $id)->first();
        if (empty($file)) {
            return response()->json([
                'result' => 'file not exist'
            ], 400);
        }
        if (!Storage::disk('local')->exists($file->path)) {
            return response()->json([
                'result' => 'file not exist'
            ], 400);
        }

        if ($this->isFileRelevant(Auth::user()->id . '/' . $validator->validated()['file'])) {
            return response()->download(
                Storage::disk('local')->path($file->path)
            );
        }
        return response()->json([
            'result' => 'file not exist'
        ], 400);

    }

    /**
     * Функция для получения объема файлов пользователя(http)
     *
     * @return JsonResponse
     */
    public function getUserFilesSize(): JsonResponse
    {
        return response()->json([
            'size' => $this->userFilesSize()
        ]);
    }

    /**
     * Функция для получения объема файлов пользователя внутри директории
     *
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
     * Функция для получения объема файлов пользователя(int)
     *
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
     * Функция для проверки актуальности файла
     *
     * @param string $filename
     * @return bool
     */
    private function isFileRelevant(string $filename): bool
    {
        $file = FileCleaning::where('path', $filename)->first();
        if (empty($file)) {
            return false;
        }
        if (strtotime('now') >= strtotime($file->deleteAt)) {

            return false;
        }

        return true;
    }

}
