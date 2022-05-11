<?php

/*
Plugin Name: Ninja Package
Plugin URI: https://github.com/zef-dev/ninja-package
Description: This plugin adds a package for Convoworks and allows us to fill out Ninja Forms.
Version: 1.0
Author: Marko Bakac
Author URI: https://github.com/MarkoBakac
License: A "Slug" license name e.g. GPL3
*/

require_once __DIR__.'/vendor/autoload.php';

/**
 * @param Convo\Core\Factory\PackageProviderFactory $packageProviderFactory
 * @param Psr\Container\ContainerInterface $container
 */
function ninja_package_register($packageProviderFactory, $container) {
	$packageProviderFactory->registerPackage(
		new Convo\Core\Factory\FunctionPackageDescriptor(
			'\Convo\Pckg\NinjaPackage\NinjaPackageDefinition',
			function() use ( $container) {
				return new \Convo\Pckg\NinjaPackage\NinjaPackageDefinition( $container->get('logger'));
			}));
}
add_action( 'register_convoworks_package', 'ninja_package_register', 10, 2);
