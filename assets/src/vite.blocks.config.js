import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import wordPressAssetPlugin from './plugins/wordpress-asset';

// Configurações de módulos externos do WordPress e WooCommerce
const externalDeps = {
    '@woocommerce/blocks-registry': 'wc.blocks.registry',
    '@woocommerce/settings': 'wc.settings',
    '@woocommerce/blocks-checkout': 'wc.blocks.checkout',
    '@wordpress/element': 'wp.element',
    '@wordpress/i18n': 'wp.i18n',
    '@wordpress/html-entities': 'wp.htmlEntities',
    '@wordpress/blocks': 'wp.blocks',
    '@wordpress/block-editor': 'wp.blockEditor',
    'jquery': 'jQuery',
    'wp': 'wp'
};

// Configuração específica para WooCommerce Blocks
export default defineConfig(({ command, mode }) => {
    const isProduction = mode === 'production';
    const isDevelopment = mode === 'development';

    return {
        build: {
            outDir: '../dist/js',
            emptyOutDir: false,
            minify: isProduction,
            sourcemap: isDevelopment,
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