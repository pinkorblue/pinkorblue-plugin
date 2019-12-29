<?php

namespace Robera\AB;

require_once('fixed-model.php');

class RemoteRequest extends FixedModel
{
    /**
     * Columns that can be edited - IE not primary key or timestamps if being used
     */
    public $table = AB_PLUGIN_PREFIX . '_remote_requests';

    protected $fillable = [
    ];

    public function populate($type, $data)
    {
        $this->type = $type;
        $this->data = $data;
        $this->iid = self::generateInteractionID();
        $this->save();
    }

    public static function generateInteractionID()
    {
        return bin2hex(random_bytes(24));
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
