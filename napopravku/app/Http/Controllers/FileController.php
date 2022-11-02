<?php

namespace App\Http\Controllers;


use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
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
        if($request->file('file')->getSize() >= 20 * pow(2,20))
        {
            return response("File larger than 20mb", Response::HTTP_BAD_REQUEST);
        }
        if($request->file('file')->extension() != "php")
        {
            return response("Php extension is not allowed", Response::HTTP_BAD_REQUEST);
        }
        if($this->userFilesSize() >= 100 * pow(2,20))
        {
            return response("User file limit exceeded", Response::HTTP_BAD_REQUEST);
        }
        Storage::disk('local')->put(Auth::user()->name . '/' . $request->file('file')->getFilename());
    }

    public function createDirectory(Request $request)
    {
        File::makeDirectory($request->folder_name);
    }

    public function download(Request $request)
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
    public function getUserFilesSize()
    {
        return $this->getUserFilesSize();
    }
    public function getUserFilesSizeInsideDirectory()
    {

    }

    protected function userFilesSize()
    {

    }
}
