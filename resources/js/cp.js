import AuditPage from './components/AuditPage.vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

Statamic.booting(() => {
  Statamic.component('audit-page', AuditPage);
});
