import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import viteImagemin from 'vite-plugin-imagemin';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import wordPressAssetPlugin from './plugins/wordpress-asset.js';

// Entradas separadas para JS e SCSS
const jsEntries = {
    'js/frontend.min':  './js/interface/frontend.js',
    'js/backend.min':   './js/interface/backend.js',
    'js/admin.min':     './js/interface/admin.js'
};

const cssEntries = {
    'css/frontend': './scss/frontend.scss',
    'css/backend':  './scss/backend.scss',
    'css/admin':    './scss/admin.scss'
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
            minify: true,
            sourcemap: isDevelopment,
            watch: isDevelopment && isBuild ? {} : null,
            rollupOptions: {
                input: { ...jsEntries, ...cssEntries },
                output: {
                    format: 'es',
                    // JS: força .js, CSS: Vite gera .css automaticamente
                    entryFileNames: (chunkInfo) => {
                        if (chunkInfo.name && chunkInfo.name.startsWith('css/')) {
                            return '[name].css';
                        }
                        return '[name].js';
                    },
                    assetFileNames: (assetInfo) => {
                        if (assetInfo.name && assetInfo.name.startsWith('css/')) {
                            return '[name].min[extname]';
                        }
                        return '[name][extname]';
                    }
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
                /*hmr: {
                    protocol: 'wss',
                    host: 'wordpress.sandbox.local',
                },*/
                proxy: {
                    '^(?!/(@vite|@react-refresh|node_modules|src|js|scss|__vite_ping))': {
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
                        format: 'iife',
                        entryFileNames: '[name].js',
                        assetFileNames: '[name][extname]',
                        globals: externalDeps
                    }
                }
            }
        } : {}),
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