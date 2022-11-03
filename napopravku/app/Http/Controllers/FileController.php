<?php

namespace App\Http\Controllers;


use App\Models\FileCleaning;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use function PHPUnit\Framework\isEmpty;

class FileController extends Controller
{
    public function __construct() {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //'image' => 'required|mimes:jpeg'
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request)
    {
        if ($request->file('file')->getSize() >= 20 * pow(2, 20)) {
            return response("File larger than 20mb", Response::HTTP_BAD_REQUEST);
        }
        if ($request->file('file')->extension() == "php") {
            return response("Php extension is not allowed", Response::HTTP_BAD_REQUEST);
        }
        if ($this->userFilesSize() >= 100 * pow(2, 20)) {
            return response("User file limit exceeded", Response::HTTP_BAD_REQUEST);
        }
        Storage::disk('local')->put(Auth::user()->id . '/' . $request->file('file')->getClientOriginalName(),
            $request->file('file')->getContent());
    }

    public function createDirectory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'directory' => 'required|string',
        ]);
        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }

        if(str_contains($validator->validated()['directory'],'/'))
        {
            return response('subdirectory not allowed', 400);
        }

        Storage::disk('local')->makeDirectory($validator->validated()['directory']);
    }

    public function download(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|string',
        ]);
        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }
        if($this->isFileRelevant(Auth::user()->id . '/' . $validator->validated()['file'])){
            return response()->download(
                Storage::disk('local')->get(Auth::user()->id . '/' . $validator->validated()['file'])
            );
        }
    }


    /**
     * Display the specified resource.
     *
     */
    public function showUserFiles()
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
    public function rename(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|string',
            'new_file' => 'required|string',
        ]);
        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }
        Storage::disk('local')->move(Auth::user()->id . '/' . $validator->validated()['file'],
            Auth::user()->id . '/' . $validator->validated()['new_file']);
        return response("file successfully renamed",200);
    }

    /**
     */
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|string',
        ]);
        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }
        if(!Storage::disk('local')->exists(Auth::user()->id . '/' . $validator->validated()['file']))
        {
            return response("file not exists",400);
        }

        Storage::disk('local')->delete(Auth::user()->id . '/' . $validator->validated()['file']);
        return response("file successfully delete",200);
    }

    public function generateFilePublicLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|string',
        ]);
        $hash = Hash::make($validator->validated()['file']);

        return Storage::disk('public')->put($hash . '/' . $validator->validated()['file'],
            Storage::disk('local')->get($validator->validated()['file']));
    }

    public function getUserFilesSize()
    {
        return response()->json([
            'size' => $this->userFilesSize()
        ]);
    }

    public function getUserFilesSizeInsideDirectory(Request $request)
    {
        $result = 0;
        $files = Storage::disk('local')->allFiles(Auth::user()->id . '/' . $request->input('directory'));
        foreach ($files as $file)
        {
            $result += Storage::disk('local')->size($file);
        }
        return response()->json([
            'size' => $result
        ]);
    }

    private function userFilesSize()
    {
        $result = 0;
        $files = Storage::disk('local')->allFiles(Auth::user()->id);
        foreach ($files as $file)
        {
            $result += Storage::disk('local')->size($file);
        }
        return $result;

    }

    private function isFileRelevant(string $filename):bool
    {
        $file = FileCleaning::Where('filename', $filename)->first();
        if(isEmpty($file))
        {
            return false;
        }
        if($file->createdAt <= $file->deleteAt)
        {
            return false;
        }
        return true;
    }
}
