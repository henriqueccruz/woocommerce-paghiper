import { defineConfig } from 'vite';
import viteImagemin from 'vite-plugin-imagemin';
import { resolve } from 'path';

// Mapeamento de dependências do WooCommerce
const wcDepMap = {
    '@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
    '@woocommerce/settings': ['wc', 'wcSettings']
};

// Mapeamento de handles do WooCommerce
const wcHandleMap = {
    '@woocommerce/blocks-registry': 'wc-blocks-registry',
    '@woocommerce/settings': 'wc-settings'
};

export default defineConfig({
    build: {
        // Output para a pasta dist
        outDir: '../dist',
        // Não limpar a pasta dist completamente para preservar arquivos estáticos
        emptyOutDir: false,
        // Configuração de build
        rollupOptions: {
            input: {
                'js/frontend.min': './js/frontend.js',
                'js/backend.min': './js/backend.js',
                'js/blocks.min': './js/blocks/woocommerce/index.js',
                'css/frontend.min': './scss/frontend.scss',
                'css/backend.min': './scss/backend.scss'
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name][extname]',
                // Configuração para dependências externas do WordPress/WooCommerce
                format: 'iife',
                globals: {
                    ...wcDepMap,
                    wp: 'wp',
                    jquery: 'jQuery'
                }
            },
            external: [
                ...Object.keys(wcDepMap),
                'wp',
                'jquery'
            ]
        },
        sourcemap: true,
        minify: true
    },
    // Servidor de desenvolvimento
    server: {
        // Servir arquivos da pasta dist
        origin: '../dist'
    },
    plugins: [
        // Otimização de imagens (apenas para novas imagens em src)
        viteImagemin({
            gifsicle: {
                optimizationLevel: 3
            },
            mozjpeg: {
                quality: 85
            },
            pngquant: {
                quality: [0.8, 0.9],
                speed: 4
            },
            svgo: {
                plugins: [
                    { name: 'removeViewBox' },
                    { name: 'removeEmptyAttrs', active: false }
                ]
            }
        })
    ],
    css: {
        preprocessorOptions: {
            scss: {
                includePaths: ['node_modules']
            }
        }
    }
});