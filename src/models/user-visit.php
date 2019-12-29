<?php

namespace Robera\AB;

require_once('fixed-model.php');

class UserVisit extends FixedModel
{
    /**
     * Columns that can be edited - IE not primary key or timestamps if being used
     */
    public $table = AB_PLUGIN_PREFIX . '_user_visits';

    protected $fillable = [
        'user_id',
        'variant_id',
        'did_interact'
    ];

    public function populate($user_id, $variant_id, $did_interact = false)
    {
        $this->user_id = $user_id;
        $this->variant_id = $variant_id;
        $this->did_interact = $did_interact;
        $this->save();
    }

    /**
     * Disable created_at and update_at columns, unless you have those.
     */
    public $timestamps = true;

    /**
     * Set primary key as ID, because WordPress
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * Make ID guarded -- without this ID doesn't save.
     *
     * @var string
     */
    protected $guarded = [ 'ID' ];
}
