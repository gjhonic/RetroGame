/*
 * Bootstrap подключается отдельной точкой входа только для админки
 * (templates/admin/base.html.twig) — публичные страницы (assets/app.js)
 * остаются на собственных стилях, см. .claude/rules/frontend.md.
 */
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
