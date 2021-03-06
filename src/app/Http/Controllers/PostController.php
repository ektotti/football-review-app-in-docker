<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Post;
use App\Tag;
use App\Http\Requests\PostStoreRequest;
use App\Http\Requests\PostUpdateRequest;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

use function Psy\debug;

class PostController extends Controller
{

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('create_post');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(PostStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $fixtureId = $request->session()->get('fixture_id');
            $user = Auth::user();
            $post = new Post;
            $columns = [
                'user_id' => $user->id,
                'fixture_id' => $fixtureId,
                'title' => $request->title,
                'body' => $request->textContent,
            ];

            for ($i = 0; $i < count($request->images); $i++) {
                $image_number = $i + 1;
                $imageData = $request->images[$i];
                $imageData = preg_replace("/data\:image\/jpeg\;base64,/", '', $imageData);
                $image = base64_decode($imageData);
                $fileName = Uuid::uuid4() . '.jpeg';
                Storage::disk('s3')->put("posts/{$fileName}", $image);
                $url = Storage::disk('s3')->url("posts/{$fileName}");
                $columns["image{$image_number}"] = $url;
            }

            $insertedPost = $post->create($columns);

            $tagIds = Tag::saveTagsAndGetIdsFromText($request->body);
            if ($tagIds) {
                $insertedPost->tags()->sync($tagIds);
            }
            DB::commit();
        } catch (Exeption $e) {
            DB::rollBack();
            report($e->getMessage());
            return json_decode($e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $post = Post::with(['user', 'fixture', 'comments.user', 'likes'])->get()->find($id);
        $isSelf = $post->checkIsSelf();
        $likeThisPost = $post->checkUserLikePost();

        return view('post_detail', compact('post', 'likeThisPost', 'isSelf'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(PostUpdateRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            $selectedPost = Post::where('id', $id)->first();
            $selectedPost->body = $request->body;
            $selectedPost->title = $request->title;
            $selectedPost->save();

            $tagIds = Tag::saveTagsAndGetIdsFromText($request->body);
            if ($tagIds) {
                $selectedPost->tags()->sync($tagIds);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            report($e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $post = Post::find($id);
            $result = Post::destroy($id);
            if (!$result) {
                throw new Exception('??????????????????????????????????????????????????????????????????????????????');
            }

            $imagePath = [];
            for ($i = 1; $i <= 4; $i++) {
                if ($post["image$i"]) {
                    $image = str_replace(env('AWS_BUCKET_URL'), '', $post["image$i"]);
                    array_push($imagePath, $image);
                }
            }
            $result = Storage::disk('s3')->delete($imagePath);
            if (!$result) {
                throw new Exception('??????????????????????????????????????????????????????????????????????????????');
            }
            
            DB::commit();
            return redirect('/');
        } catch (Exception $e) {
            DB::rollBack();
            echo "?????????" . $e->getMessage();
        }
    }
}
