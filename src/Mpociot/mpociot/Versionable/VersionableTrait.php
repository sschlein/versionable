<?php
namespace Mpociot\Versionable;

/**
 * Class VersionableTrait
 * @package Mpociot\Versionable
 */
trait VersionableTrait
{

    /**
     * @var bool
     */
    private $updating;

    /**
     * Initialize model events
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->versionablePreSave();
        });

        static::saved(function ($model) {
            $model->versionablePostSave();
        });

    }

    public function versions()
    {
        return $this->morphMany('\Mpociot\Versionable\Version', 'versionable');
    }

    /**
     * @param $version_id
     * @return null
     */
    public function getVersionModel( $version_id )
    {
        $version = $this->versions()->where("version_id","=", $version_id )->first();
        if( !is_null( $version) )
        {
            return $version->getModel();
        } else {
            return null;
        }
    }

    /**
     * Pre save hook to determine if versioning is enabled and if we're updating
     * the model
     */
    public function versionablePreSave()
    {
        if( !isset( $this->versioningEnabled ) || $this->versioningEnabled === true )
        {
            $this->updating     = $this->exists;
        }
    }

    /**
     * Save a new version
     */
    public function versionablePostSave()
    {
        /**
         * We'll save new versions on updating and first creation
         */
        if(
            ( (!isset( $this->versioningEnabled ) || $this->versioningEnabled === true) && $this->updating && $this->validForVersioning() ) ||
            ( (!isset( $this->versioningEnabled ) || $this->versioningEnabled === true) && !$this->updating )
        )
        {
            // Save a new version
            $version                    = new Version();
            $version->versionable_id    = $this->getKey();
            $version->versionable_type  = get_class( $this );
            $version->user_id           = $this->getAuthUserId();
            $version->model_data        = serialize( $this->toArray() );
            $version->save();
        }
    }

    private function validForVersioning()
    {
        $versionableData = $this->getDirty();
        unset( $versionableData[ $this->getUpdatedAtColumn() ] );
        if( function_exists('getDeletedAtColumn') )
        {
            unset( $versionableData[ $this->getDeletedAtColumn() ] );
        }

        if( isset($this->dontVersionFields) )
        {
            foreach( $this->dontVersionFields AS $fieldName )
            {
                unset( $versionableData[ $fieldName ] );
            }
        }
        return ( count( $versionableData ) > 0 );
    }

    /**
     * @return int|null
     */
    private function getAuthUserId()
    {
        if( \Auth::check() )
        {
            return \Auth::id();
        }
        return null;
    }



}