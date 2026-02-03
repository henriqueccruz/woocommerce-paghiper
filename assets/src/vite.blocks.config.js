import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import wordPressAssetPlugin from './plugins/wordpress-asset';

// Configurações de módulos externos do WordPress e WooCommerce
const externalDeps = {
	'@woocommerce/blocks-registry'  : 'window.wc.wcBlocksRegistry',
	'@woocommerce/settings'       	: 'window.wc.wcSettings',
	'@woocommerce/blocks-checkout'  : 'window.wc.blocksCheckout',
	'@wordpress/element'            : 'wp.element',
	'@wordpress/i18n'               : 'wp.i18n',
	'@wordpress/html-entities'      : 'wp.htmlEntities',
	'@wordpress/blocks'             : 'wp.blocks',
	'@wordpress/block-editor'       : 'wp.blockEditor',
	'jquery'                        : 'jQuery',
	'wp'                            : 'wp'
};

// Configuração específica para WooCommerce Blocks
export default defineConfig(({ command, mode }) => {
	const isProduction = mode === 'production';
	const isDevelopment = mode === 'development';

	const commonConfig = {
		optimizeDeps: {
			exclude: Object.keys(externalDeps)
		}
	};

	return {
		...commonConfig,
		build: isDevelopment ? {
			outDir: '../dist/js',
			emptyOutDir: false,
			sourcemap: true,
			minify: false,
			rollupOptions: {
				input: './js/blocks/woocommerce/index.jsx',
				external: Object.keys(externalDeps),
				output: {
					format: 'es',
					name: 'PaghiperBlocks',
					globals: externalDeps,
					entryFileNames: 'blocks.min.js'
				}
			}
		} : {
			outDir: '../dist/js',
			emptyOutDir: false,
			minify: true,
			sourcemap: false,
			lib: {
				entry: './js/blocks/woocommerce/index.jsx',
				formats: ['iife'],
				name: 'PaghiperBlocks',
				fileName: () => 'blocks.min.js'
			},
			rollupOptions: {
				external: Object.keys(externalDeps),
				output: {
					globals: externalDeps
				}
			}
		},
		server: isDevelopment ? {
			port: 5174,
			base: '/blocks/',
			host: 'wordpress.sandbox.local',
			https: {
				key: './certificates/wordpress.sandbox.local+3-key.pem',
				cert: './certificates/wordpress.sandbox.local+3.pem'
			},
			watch: {
				include: ['./js/blocks/**/*.{js,jsx}'],
				exclude: ['node_modules/**', 'dist/**']
			},
			hmr: {
				protocol: 'wss',
				host: 'wordpress.sandbox.local',
				clientPort: 5174,
			},
			cors: {
				origin: '*',
			},
			middlewareMode: false
		} : {},
		plugins: [
			react({
				include: [/\.(jsx|js)$/],
				babel: {
					presets: ['@babel/preset-react']
				}
			}),
			wordPressAssetPlugin()
		]
	};
});