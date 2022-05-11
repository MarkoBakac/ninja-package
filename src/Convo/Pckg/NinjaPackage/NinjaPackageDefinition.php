<?php declare(strict_types=1);

namespace Convo\Pckg\NinjaPackage;

use Convo\Core\Factory\AbstractPackageDefinition;
use Convo\Core\Intent\EntityModel;
use Convo\Core\Intent\SystemEntity;
use Convo\Core\Expression\ExpressionFunction;

class NinjaPackageDefinition extends AbstractPackageDefinition
{
	const NAMESPACE	=	'ninja-package';
    private $_wpdb;

	public function __construct(
		\Psr\Log\LoggerInterface $logger
	) {
        global $wpdb;
        $this->_wpdb = $wpdb;
		parent::__construct( $logger, self::NAMESPACE, __DIR__);
	}

	protected function _initDefintions()
	{
		return array (
            new \Convo\Core\Factory\ComponentDefinition(
                $this->getNamespace(),
                '\Convo\Pckg\NinjaPackage\NinjaFormContext',
                'Ninja Forms Context',
                'Ninja Forms managing context',
                array(
                    'id' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => 'ninja_forms_ctx',
                        'name' => 'Context ID',
                        'description' => 'Unique ID by which this context is referenced',
                        'valueType' => 'string'
                    ),
                    'form_id' => array(
                        'editor_type' => 'text',
                        'editor_properties' => [],
                        'defaultValue' => null,
                        'name' => 'Form ID',
                        'description' => 'ID or form key of the form you will work with.',
                        'valueType' => 'string'
                    ),
                    'user_id' => array(
                        'editor_type' => 'text',
                        'editor_properties' => [],
                        'defaultValue' => null,
                        'name' => 'User ID',
                        'description' => 'Optional user id to use when inserting',
                        'valueType' => 'string'
                    ),
                    '_preview_angular' => array(
                        'type' => 'html',
                        'template' => '<div class="code">' .
                            '<span class="statement">Ninja form </span> <b>[{{ contextElement.properties.id }}]</b>' .
                            '</div>'
                    ),
                    '_workflow' => 'datasource',
                    '_help' =>  array(
                        'type' => 'file',
                        'filename' => 'dummy-form-context.html'
                    ),
                    '_factory' => new class ($this->_wpdb) implements \Convo\Core\Factory\IComponentFactory
                    {
                        private $_wpdb;
                        public function __construct( $wpdb)
                        {
                            $this->_wpdb = $wpdb;
                        }
                        public function createComponent($properties, $service)
                        {
                            return new NinjaFormContext( $properties, $this->_wpdb);
                        }
                    }
                )
            ),
		);
	}
}
