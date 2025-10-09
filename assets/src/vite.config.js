import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import viteImagemin from 'vite-plugin-imagemin';
import { viteStaticCopy } from 'vite-plugin-static-copy';

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
}

// Lista de entradas para compilar
const entries = {
    'js/frontend.min':  './js/interface/frontend.js',
    'js/backend.min':   './js/interface/backend.js',
    'js/admin.min':     './js/interface/admin.js',
    'js/blocks.min':    './js/blocks/woocommerce/index.jsx',
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
                    globals: externalDeps,
                    entryFileNames: '[name].js',
                    assetFileNames: '[name][extname]'
                }
            }
        },
        ...(isDevelopment && !isBuild ? {
            server: {
                host: 'wordpress.sandbox.local',
                https: {
                    key: './certificates/wordpress.sandbox.local+3-key.pem',
                    cert: './certificates/wordpress.sandbox.local+3.pem'
                },
                watch: {
                    include: ['./js/**/*.{js,jsx}', './scss/**/*.scss'],
                    exclude: ['node_modules/**', 'dist/**']
                },
                proxy: {
                    '^/': {
                        target: 'https://wordpress.sandbox.local',
                        changeOrigin: true,
                        secure: false
                    }
                },
                cors: true,
                hmr: {
                    protocol: 'wss',
                    host: 'wordpress.sandbox.local',
                    clientPort: 5173
                }
            },
        } : {}),
        plugins: [
            react(),
            {
                name: 'wrap-in-iife',
                generateBundle(options, bundle) {
                    Object.keys(bundle).forEach(fileName => {
                        const file = bundle[fileName];
                        if (file.type === 'chunk' && fileName.endsWith('.js')) {
                            file.code = `(function(){${file.code}})();`;
                        }
                    });
                }
            },
            viteStaticCopy({
                targets: [
                    {
                        src: './static/*',
                        dest: '../dist'
                    },
                    {
                        src: './images/*',
                        dest: '../dist/images'
                    }
                ]
            }),
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