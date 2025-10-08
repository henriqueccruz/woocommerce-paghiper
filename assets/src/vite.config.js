import { defineConfig } from 'vite';
import viteImagemin from 'vite-plugin-imagemin';
import { viteStaticCopy } from 'vite-plugin-static-copy';
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
                'js/frontend.min': './js/interface/frontend.js',
                'js/backend.min': './js/interface/backend.js',
                'js/admin.min': './js/interface/admin.js',
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
        // Proxy para o WordPress
        proxy: 'https://wordpress.sandbox.local',
        // Permite HTTPS
        https: true,
        // Cors para desenvolvimento
        cors: true,
        // Hot Module Replacement
        hmr: {
            // Força WebSocket sobre HTTPS
            protocol: 'wss',
            // Host para conexão WebSocket
            host: 'wordpress.sandbox.local'
        },
        // Watchfiles adicionais
        watch: {
            // Observa mudanças em arquivos PHP do plugin
            include: [
                '../../../**/*.php'
            ]
        }
    },
    plugins: [
        // Otimização de imagens
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
        }),
        // Cópia de arquivos estáticos
        viteStaticCopy({
            targets: [
                {
                    src: 'js/libs/**/*',
                    dest: '../dist/js/libs'
                },
                {
                    src: ['fonts/*', 'php/*'],
                    dest: '../dist'
                }
            ]
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