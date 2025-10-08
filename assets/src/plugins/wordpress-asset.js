import fs from 'fs';
import path from 'path';

/**
 * Plugin Vite para gerar o arquivo de dependências do WordPress
 * Similar ao que o @wordpress/scripts faz
 */
export default function wordPressAssetPlugin() {
    return {
        name: 'wordpress-asset',
        writeBundle(options, bundle) {
            const dependencies = [
                'wp-blocks',
                'wp-element',
                'wp-i18n',
                'wp-block-editor',
                'wc-settings',
                'wc-blocks-registry',
                'wc-blocks-checkout'
            ];

            const assetFile = {
                dependencies,
                version: process.env.npm_package_version || '1.0.0'
            };

            // Criar o conteúdo do arquivo PHP
            const content = `<?php
/**
 * Arquivo gerado automaticamente pelo build do Vite.
 * NÃO EDITAR DIRETAMENTE.
 */
return ${JSON.stringify(assetFile, null, 2)};`;

            // Garantir que o diretório dist existe
            const outputDir = path.resolve(__dirname, '../../../includes/integrations/woocommerce-blocks');
            if (!fs.existsSync(outputDir)) {
                fs.mkdirSync(outputDir, { recursive: true });
            }

            // Escrever o arquivo
            fs.writeFileSync(
                path.resolve(outputDir, 'blocks.asset.php'),
                content
            );
        }
    };
}