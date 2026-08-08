import './stimulus_bootstrap.js';
import { registerVueControllerComponents } from '@symfony/ux-vue/dist/loader.js';

/*
 * CSS is loaded per-module via <link> tags (see templates/base.html.twig and
 * the module-specific base templates), not from here — see
 * assets/styles/common.css and assets/styles/public/.
 */

const vueModules = import.meta.glob('./vue/**/*.vue', { eager: true });
const vueControllers = Object.fromEntries(
    Object.entries(vueModules).map(([path, module]) => [
        path.replace('./vue/', '').replace('.vue', ''),
        module.default,
    ]),
);

registerVueControllerComponents(vueControllers);
