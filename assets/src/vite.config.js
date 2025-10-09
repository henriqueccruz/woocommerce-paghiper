import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import viteImagemin from 'vite-plugin-imagemin';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import wordPressAssetPlugin from './plugins/wordpress-asset.js';

// Configurações de módulos externos do WordPress e WooCommerce
const externalDeps = {
    '@woocommerce/blocks-registry'  : 'window.wc.wcBlocksRegistry',
    '@woocommerce/settings'         : 'window.wc.wcSettings',
    '@woocommerce/blocks-checkout'  : 'window.wc.blocksCheckout',
    '@wordpress/element'            : 'wp.element',
    '@wordpress/i18n'               : 'wp.i18n',
    '@wordpress/html-entities'      : 'wp.htmlEntities',
    '@wordpress/blocks'             : 'wp.blocks',
    '@wordpress/block-editor'       : 'wp.blockEditor',
    'jquery'                        : 'jQuery',
    'wp'                            : 'wp'
};

// Lista de entradas para compilar
const entries = {
    'js/frontend.min':  './js/interface/frontend.js',
    'js/backend.min':   './js/interface/backend.js',
    'js/admin.min':     './js/interface/admin.js',
    'js/blocks.min':    './js/blocks/woocommerce/index.jsx',
    'js/hello.min':     './js/blocks/woocommerce/hello.jsx',
    'css/frontend.min': './scss/frontend.scss',
    'css/backend.min':  './scss/backend.scss',
    'css/admin.min':    './scss/admin.scss'
};

export default defineConfig(({ command, mode }) => {
    const isProduction = mode === 'production';
    const isDevelopment = mode === 'development';
    const isBuild = command === 'build';

    return {
        base: './',
        build: {
            outDir: '../dist',
            emptyOutDir: false,
            minify: isProduction,
            sourcemap: isDevelopment,
            watch: isDevelopment && isBuild ? {} : null,
            rollupOptions: {
                input: entries,
                external: Object.keys(externalDeps),
                output: {
                    format: 'es',
                    entryFileNames: '[name].js',
                    assetFileNames: '[name][extname]',
                    globals: externalDeps
                }
            }
        },
        ...(isDevelopment && !isBuild ? {
            server: {
                host: 'wordpress.sandbox.local',
                port: 5173,
                https: {
                    key: './certificates/wordpress.sandbox.local+3-key.pem',
                    cert: './certificates/wordpress.sandbox.local+3.pem'
                },
                origin: 'https://wordpress.sandbox.local:5173',
                cors: true,
                hmr: {
                    protocol: 'wss',
                    host: 'wordpress.sandbox.local',
                },
                proxy: {
                    '^(?!/(@vite|node_modules|src|js|scss|__vite_ping))': {
                        target: 'https://wordpress.sandbox.local',
                        changeOrigin: true,
                        secure: false
                    }
                }
                /*watch: {
                    include: ['dist/**'],
                    exclude: ['node_modules/**', '.DS_Store']
                },*/
            },
            // Build automático no servidor de desenvolvimento
            build: {
                outDir: '../dist',
                emptyOutDir: true,
                minify: false,
                sourcemap: true,
                watch: {},
                rollupOptions: {
                    input: entries,
                    output: {
                        format: 'es',
                        entryFileNames: '[name].js',
                        assetFileNames: '[name][extname]'
                    }
                }
            }
        } : {}),
        esbuild: {
            // Diz ao esbuild como criar os elementos JSX
            jsxFactory: 'React.createElement',
            jsxFragment: 'React.Fragment',

            loader: "jsx",

            include: [
                // Adicione extensões que você quer que sejam tratadas como JSX
                "src/**/*.jsx",
            ],
            exclude: [],
        },
        plugins: [
            react(),
            wordPressAssetPlugin(),
            {
                name: 'wrap-js-in-iife',
                generateBundle(options, bundle) {
                    Object.keys(bundle).forEach(fileName => {
                        const file = bundle[fileName];
                        // Apenas arquivos JS, não CSS
                        if (file.type === 'chunk' && fileName.endsWith('.js') && !fileName.includes('css')) {
                            file.code = `(function(){${file.code}})();`;
                        }
                    });
                }
            },
            // Otimização de imagens apenas em produção
            viteImagemin({
                disable: isDevelopment,
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
    };
});