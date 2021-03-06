<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $guarded = [
        'id'
    ];

    public function posts() {
        return $this->belongsTo('App\Post');
    }

    public function user() {
        return $this->belongsTo('App\User', 'comment_user_id', 'id');
    }
}
