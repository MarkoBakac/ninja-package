<?php
namespace Convo\Pckg\NinjaPackage;

use Convo\Core\Workflow\AbstractBasicComponent;
use Convo\Core\Workflow\IServiceContext;
use Convo\Core\Params\IServiceParamsScope;
use Convo\Core\Util\StrUtil;
use Convo\Core\DataItemNotFoundException;
use Convo\Pckg\Forms\FormValidationResult;
use Convo\Pckg\Forms\FormValidationException;
use Convo\Pckg\Forms\IFormsContext;
use Convo\Pckg\NinjaPackage\NinjaPrepareEntry;
use NF_Fields_Textarea;

class NinjaFormContext extends AbstractBasicComponent implements IServiceContext, IFormsContext
{
    private $_id;
    private $_formId;
    private $_userId;
    /**
     * @var \wpdb
     */
    private $_wpdb;

    public function __construct( $properties, $wpdb)
    {
        parent::__construct( $properties);

        $this->_id = $properties['id'];
        $this->_formId = $properties['form_id'];
        $this->_userId = $properties['user_id'];
        $this->_wpdb = $wpdb;
    }
	
    
    /**
     * @return mixed
     */
    public function init()
    {
        $this->_logger->info( 'Initializing ['.$this.']');
    }
    
    /**
     * @return mixed
     */
    public function getComponent()
    {
        return $this;
    }
    
    public function getId()
    {
        return $this->_id;
    }
    
    // FORMS
    public function validateEntry($entry)
    {
        $fi = new NinjaPrepareEntry();
        $errors = $fi->NinjaPrepareEntry($entry);

        $this->_logger->info(print_r($errors, true));

        $result = new FormValidationResult();

        foreach ( $errors as $key=>$val) {
            if ( $key === 'form') {
                throw new \Exception( $val);
            }
            if ( $key === 'spam') {
                $this->_logger->warning( 'Ignoring antispam message ['.$val.']');
                continue;
            }
            $result->addError( $key, $val);
        }
        $this->_logger->info( 'Result ['.print_r( $result, true).']');
        return $result;
    }

    public function createEntry($entry)
    {
        $this->_checkEntry( $entry);

        $this->getUserId();

        $entry_form = Ninja_Forms()->form($this->getFormId())->sub()->get();

        $this->_logger->info( 'Inserting form ['.print_r( $entry, true).']');

        $entry_form->update_field_values( $entry )->save();

        $latest = \get_posts("post_type=nf_sub&numberposts=1");
        $entry_id = $latest[0]->ID;

        $this->_logger->info( 'THE ENTRY ID : '.print_r( $entry_id, true));

        if ( !$entry_id) {
            throw new \Exception( 'Error while inserting entry');
        }

        return $entry_id;
    }
    
    public function deleteEntry( $entry_id)
    {
        $this->getUserId();

        $sub=Ninja_Forms()->form( $this->getFormId() )->sub($entry_id)->get();

        $sub->delete();
    }
    
    public function updateEntry( $entry_id, $entry)
    {
        $this->_checkEntry( $entry);

        $this->getUserId();

        $sub = Ninja_Forms()->form($this->getFormId())->sub()->get();
        $sub->update_field_values( $entry )->save();
        $sub->save();

        $del = Ninja_Forms()->form(2)->sub($entry_id)->get();
        $del->delete();

        $latest = \get_posts("post_type=nf_sub&numberposts=1");
        $entry_id = $latest[0]->ID;

        $this->_logger->info( 'THE ENTRY ID : '.print_r( $entry_id, true));

        if ( !$entry_id) {
            throw new \Exception( 'Error while inserting entry');
        }

        return $entry_id;
    }
    
    public function searchEntries( $search, $offset=0, $limit=self::DEFAULT_LIMIT, $orderBy=[])
    {
        $this->getUserId();

        $query  =   '
    SELECT p.*
    FROM '.$this->_wpdb->prefix.'posts p ';

        $query .=    $this->_buildWhere( $search);
        $query .=    $this->_buildOrderBy( $orderBy);
        $query .=    ' 
        LIMIT '.$offset.', '.$limit;

        $this->_logger->debug( 'Got query ['.$query.']');

        $data = $this->_wpdb->get_results( $query, ARRAY_A);

        $this->_logger->debug( 'Got last result ['.print_r( $data, true).']');

        $entries  =    [];
        foreach ($data as $row) {
            $this->_logger->debug( 'Got query :'.$row['ID'].'');
            $entries[] = $this->getEntry( $row['ID']);
        }
        return $entries;
    }

    public function getSearchCount( $search)
    {
        $query      =   'SELECT COUNT( p.ID) AS CNT FROM '.$this->_wpdb->prefix.'posts p' ;

        $this->_logger->debug( 'Got query ['.$query.']');

        $query .=   $this->_buildWhere( $search).

            $this->_logger->debug( 'Got query ['.$query.']');

        $row = $this->_wpdb->get_row( $query, ARRAY_A);

        return intval( $row['CNT']);
    }

    private function _buildWhere( $search)
    {
        $join       =   '';
        $where      =   '';

        foreach ( $search as $key=>$val)
        {
            $field_id = self::getFieldId( $key);

            $join .= '
    INNER JOIN '.$this->_wpdb->prefix.'postmeta AS '.$this->_getMetaField( $field_id).'
     ON p.ID='.$this->_getMetaField( $field_id).'.post_id';

            if ( empty( $where)) {
                $where .= '
    WHERE ';
            } else {
                $where .= '
    AND ';
            }
            $where .= 'post_type="nf_sub" AND meta_'.$field_id.'.meta_value = \''.$this->_wpdb->_real_escape( $val).'\' ';
        }

        return $join.' '.$where;
    }

    private function _buildOrderBy( $orderBy)
    {
        if ( empty( $orderBy)) {
            return '';
        }

        $order_by  =   '';
        foreach ( $orderBy as $key=>$val)
        {
            $field_id = self::getFieldId( $key);

            if ( empty( $order_by)) {
                $order_by .= ' ORDER BY ';
            } else {
                $order_by .= ', ';
            }

            $order_by .= ' '.$this->_getMetaField( $field_id).' '.$val;
        }

        $this->_logger->debug( 'Got last result ['.print_r( $order_by, true).']');

        return $order_by;
    }
    
    public function getEntry( $entry_id)
    {
        $this->getUserId();

        $sub=Ninja_Forms()->form( $this->getFormId() )->sub($entry_id)->get();

        $entry = $sub->get_field_values();

        if ( empty( $entry)) {
            throw new DataItemNotFoundException( 'Entry ['.$entry_id.'] not found');
        }

        $this->_logger->info(print_r($entry, true));

        return $entry;
    }
    
    /**
     * Throw an exception if not valid
     * @param array $entry
     * @throws FormValidationException
     */
    private function _checkEntry( $entry) 
    {
        $result =   $this->validateEntry( $entry);
        if ( !$result->isValid()) {
            throw new FormValidationException( $result);
        }
    }

    private function _getMetaField( $field)
    {
        $field_id = self::getFieldId( $field);
        return 'meta_'.$field_id.' ';
    }

    //NINJA CUSTOM
    public function getFormId()
    {
        $form_id   = $this->getService()->evaluateString( $this->_formId);
        if ( !is_numeric( $form_id)) {
            $form_id = self::getFormId();
        }

        $this->_logger->debug( 'Got form id ['.$form_id.']');

        return $form_id;
    }

    public function getUserId()
    {
        $user_id   = $this->getService()->evaluateString( $this->_userId);

        $this->_logger->debug( 'Got user id ['.$user_id.']');

        return $user_id;
    }

    public static function getFieldId( $field)
    {
        global $wpdb;

        if ( is_numeric( $field)) {
            return $field;
        }

        $field_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}nf3_fields WHERE `key` = '{$field}' AND `parent_id` = 2");


        if ( !$field_id) {
            throw new \Exception( 'Failed to get field id from ['.$field.']');
        }
        return $field_id;
    }


	// UTIL
	public function __toString()
	{
	    return parent::__toString().'['.$this->_id.']';
	}


}