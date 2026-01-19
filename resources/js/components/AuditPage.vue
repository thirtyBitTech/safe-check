<template>
  <div class="max-w-6xl">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Safe Check</h1>

      <div class="text-sm text-gray-600">
        <span class="mr-2">Last Scan:</span>
        <span v-if="lastScanAt">{{ lastScanAtHuman }}</span>
        <span v-else class="text-gray-400">Never</span>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="p-4 rounded-lg border bg-white flex items-center gap-3">
        <div class="h-10 w-10 rounded-full flex items-center justify-center bg-green-100">
          <span class="text-green-700 font-bold">✓</span>
        </div>
        <div>
          <div class="text-sm text-gray-600">Packages Scanned</div>
          <div class="text-2xl font-semibold">{{ summary.packages_scanned }}</div>
        </div>
      </div>

      <div class="p-4 rounded-lg border bg-white flex items-center gap-3">
        <div class="h-10 w-10 rounded-full flex items-center justify-center bg-yellow-100">
          <span class="text-yellow-700 font-bold">!</span>
        </div>
        <div>
          <div class="text-sm text-gray-600">Vulnerabilities Found</div>
          <div class="text-2xl font-semibold">{{ summary.vulnerabilities_found }}</div>
        </div>
      </div>

      <div class="p-4 rounded-lg border bg-white flex items-center gap-3">
        <div class="h-10 w-10 rounded-full flex items-center justify-center bg-blue-100">
          <span class="text-blue-700 font-bold">📦</span>
        </div>
        <div>
          <div class="text-sm text-gray-600">Passed Checks</div>
          <div class="text-2xl font-semibold">{{  summary.packages_scanned - summary.vulnerabilities_found }}</div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3 mb-6">
      <button
        class="btn-primary"
        :disabled="loading"
        @click="runScan"
      >
        <span v-if="loading">Scanning…</span>
        <span v-else>Run Dependency Scan</span>
      </button>

      <a
        v-if="hasScan"
        class="btn"
        :href="exportJsonUrl"
        target="_blank"
        rel="noopener"
      >
        Download Report (JSON)
      </a>

      <div v-if="error" class="text-sm text-red-600 ml-2">
        {{ error }}
      </div>
    </div>

    <!-- Report Table -->
    <div class="card p-0 overflow-hidden">
      <div class="p-4 border-b bg-gray-50">
        <div class="font-semibold">Dependency Vulnerability Report</div>
        <div class="text-sm text-gray-600 mt-1">
          {{ disclaimer }}
        </div>
      </div>

      <div v-if="!hasScan && !loading" class="p-6 text-gray-600">
        No scan has been run yet. Click <strong>Run Dependency Scan</strong> to generate a report.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full">
          <thead class="bg-white border-b">
            <tr class="text-left text-sm text-gray-600">
              <th class="px-4 py-3">Package</th>
              <th class="px-4 py-3">Installed Version</th>
              <!-- <th class="px-4 py-3">Severity</th> -->
              <th class="px-4 py-3">View details</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="(item, idx) in items"
              :key="item.id || item.package + ':' + idx"
              class="border-b last:border-b-0 bg-white"
            >
              <td class="px-4 py-3">
                <div class="font-medium">{{ item.package }}</div>
                  <a
                    v-if="item.id"
                    href="#"
                    class="text-xs text-blue-600 hover:underline mt-1 inline-block"
                    @click.prevent="openDetails(item)"
                  >
                    {{ item.id }}
                  </a>
              </td>

              <td class="px-4 py-3">
                {{ item.installed_version || '—' }}
              </td>

          

              <!-- <td class="px-4 py-3">
                <span
                  class="inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold"
                  :class="severityClass(item.severity)"
                >
                  {{ item.severity || 'Unknown' }}
                </span>
              </td> -->

              

               <td class="px-4 py-3 align-middle">
  <div class="flex items-center h-full">
    <button
      class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-semibold
             bg-blue-50 text-blue-700 border border-blue-200
             hover:bg-blue-100 hover:border-blue-300
             focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1
             transition"
      @click="openDetails(item)"
    >
      View details
    </button>
  </div>
</td>

            </tr>

            <tr v-if="hasScan && items.length === 0" class="bg-white">
              <td class="px-4 py-6 text-gray-600" colspan="5">
                No vulnerabilities were found in your composer dependencies.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>




<div
  v-if="showModal"
  class="fixed inset-0 z-50 flex items-center justify-center
         bg-black/40 px-4 py-10 overflow-y-auto"
>
<div
  class="bg-white w-full max-w-2xl rounded-xl shadow-xl relative
         max-h-[70vh] flex flex-col"
>

    <!-- Header -->
    <div class="px-6 py-4 border-b flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span
          class="px-3 py-1 rounded-md text-xs font-bold uppercase"
          :class="{
            'bg-red-100 text-red-700': activeDetails.severity === 'HIGH',
            'bg-orange-100 text-orange-700': activeDetails.severity === 'MEDIUM',
            'bg-green-100 text-green-700': activeDetails.severity === 'LOW',
            'bg-red-200 text-red-800': activeDetails.severity === 'CRITICAL',
            'bg-gray-100 text-gray-700': activeDetails.severity === 'UNKNOWN'

          }"
        >
          {{ activeDetails.severity }} Risk
        </span>

        <div class="text-lg font-semibold">
          {{ activeItem.package }}
        </div>
      </div>

      <button
        class="text-gray-400 hover:text-gray-700"
        @click="closeModal"
      >
        ✕
      </button>
    </div>

    <!-- Meta -->
    <div class="px-6 py-3 border-b text-sm text-gray-600 flex flex-wrap gap-x-6 gap-y-1">
      <div>
        Installed: <span class="font-medium text-gray-900">{{ activeDetails.installed_version }}</span>
      </div>
     <div class="flex items-center gap-2 text-sm">
  <span class="text-gray-600">Advisory:</span>

  <a
    :href="`https://github.com/advisories/${activeDetails.id}`"
    target="_blank"
    rel="noopener"
    class="inline-flex items-center gap-1.5 font-mono text-blue-700
           hover:underline hover:text-blue-800
           focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1
           transition"
  >
    {{ activeDetails.id }}

    <!-- external link icon -->
    <svg
      class="h-3.5 w-3.5"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M14 3h7m0 0v7m0-7L10 14"
      />
    </svg>
  </a>
</div>

      <div>
        Published:
        <span class="font-mono text-gray-800">
          {{ activeDetails.published }}
        </span>
      </div>
    </div>

    <!-- Body -->
    <div class="p-6 overflow-y-auto flex-1 space-y-6">

      <!-- Summary -->
      <div>
        <div class="flex items-center gap-2 font-semibold mb-1">
          ⚠️ Summary
        </div>
        <p class="text-gray-700 leading-relaxed">
          {{ activeDetails.summary || 'No summary available.' }}
        </p>
      </div>

      <!-- Why it matters -->
      <div>
        <div class="flex items-center gap-2 font-semibold mb-2">
          🧠 Why this matters
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-900">
          {{ activeDetails.why_it_matters }}
        </div>
      </div>

      <!-- What to do -->
      <div>
        <div class="flex items-center gap-2 font-semibold mb-2">
          ✅ What to do now
        </div>

        <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-900 font-medium">
          {{ activeDetails.next_step }}
        </div>
      </div>

      <!-- Technical details (collapsible) -->
      <div v-if="activeDetails.details">
        <button
          class="flex items-center gap-2 font-semibold text-gray-800 hover:text-gray-900"
          @click="showFullDetails = !showFullDetails"
        >
          <span
      class="transition-transform"
      :class="{ 'rotate-100': showFullDetails }"
    >
      ▶
    </span>  Technical details
        </button>

        <div
          v-if="showFullDetails"
          class="mt-3 prose prose-sm max-w-none markdown-body"
          v-html="renderMarkdown(activeDetails.details)"
        ></div>
      </div>

      <!-- Affected Versions (collapsible) -->
<div v-if="activeDetails.affected_range">
  <button
    class="flex items-center gap-2 font-semibold text-gray-800 hover:text-gray-900"
    @click="showAffectedVersions = !showAffectedVersions"
  >
    <span
      class="transition-transform"
      :class="{ 'rotate-100': showAffectedVersions }"
    >
      ▶
    </span> Affected versions
  </button>

  <div
    v-if="showAffectedVersions"
    class="mt-3 bg-gray-50 border rounded-lg p-4 text-sm text-gray-800 space-y-2"
  >
    <div class="font-mono whitespace-pre-line leading-relaxed">
      {{ activeDetails.affected_range }}
    </div>
  </div>
</div>


      <!-- References -->
      <!-- References (collapsible) -->
<div v-if="activeDetails.references?.length">
  <button
    class="flex items-center gap-2 font-semibold text-gray-800 hover:text-gray-900"
    @click="showReferences = !showReferences"
  >
    <span
      class="transition-transform"
      :class="{ 'rotate-100': showReferences }"
    >
      ▶
    </span>
    References
    <span class="text-sm text-gray-500">
      ({{ activeDetails.references.length }})
    </span>
  </button>

  <div
    v-if="showReferences"
    class="mt-3 space-y-1"
  >
    <a
      v-for="(ref, i) in activeDetails.references"
      :key="ref.url + i"
      :href="ref.url"
      target="_blank"
      rel="noopener"
      class="block border rounded-lg p-3 hover:bg-gray-50 transition"
    >
      <div class="flex items-center gap-2 text-sm">
        <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs font-semibold">
          Ref {{ i + 1 }}
        </span>

        <span v-if="ref.type" class="text-gray-500">
          {{ ref.type }}
        </span>

        <span class="text-gray-700 truncate">
          {{ prettyDomain(ref.url) }}
        </span>
      </div>

      <div class="text-xs text-gray-500 truncate mt-1">
        {{ ref.url }}
      </div>
    </a>
  </div>
</div>


    </div>

    <!-- Footer -->
    <div class="px-6 py-4 border-t flex justify-end">
      <button
        class="px-4 py-2 rounded-md bg-blue-600 text-white text-sm font-medium hover:bg-blue-700"
        @click="closeModal"
      >
        Close
      </button>
    </div>

  </div>
</div>
</div>



</template>

<script>

import { marked } from 'marked'
import DOMPurify from 'dompurify'

export default {
  data() {
    return {
      loading: false,
      error: null,
      scan: null,
      showModal: false,
      modalLoading: false,
      modalError: null,
      activeItem: null,
      activeDetails: null,
      showFullDetails: false,
      showAffectedVersions: false,
      showReferences: false,

    };
  },

  watch: {
  activeItem() {
    this.showFullDetails = false;
    this.showAffectedVersions = false;
    this.showReferences = false;
  }
},

  computed: {
    hasScan() {
      return !!this.scan && !this.scan.error;
    },

    items() {
      return (this.scan && this.scan.items) ? this.scan.items : [];
    },

    summary() {
      return {
        packages_scanned: this.scan?.packages_scanned ?? 0,
        vulnerabilities_found: this.scan?.vulnerabilities_found ?? 0,
        high_critical: this.scan?.high_critical ?? 0,
      };
    },

    disclaimer() {
      return this.scan?.disclaimer
        || 'This tool provides visibility into known dependency vulnerabilities. It does not guarantee application security.';
    },

    lastScanAt() {
      return this.scan?.scanned_at || null;
    },

    lastScanAtHuman() {
      try {
        const d = new Date(this.lastScanAt);
        return isNaN(d.getTime()) ? this.lastScanAt : d.toLocaleString();
      } catch {
        return this.lastScanAt;
      }
    },

    exportJsonUrl() {
      // Prefer Statamic-provided CP base if available
      const cp = window.Statamic?.cpUrl || '/cp';

      return `${cp}/safe-check/export/json`;
    },

     truncatedDetails() {
    if (!this.activeDetails?.details) return null;
    return this.activeDetails.details.slice(0, 400);
  },
  hasLongDetails() {
    return this.activeDetails?.details?.length > 400;
  },
    
  },

  async mounted() {
    await this.fetchLatest();
  },

  methods: {

     renderMarkdown(md) {
      if (!md) return '';

      const html = marked.parse(md, {
        breaks: true,
        gfm: true,
      });

      return DOMPurify.sanitize(html);
    },
    severityClass(sev) {
      const s = (sev || '').toLowerCase();

      if (s === 'critical') return 'bg-red-100 text-red-700';
      if (s === 'high') return 'bg-red-50 text-red-700';
      if (s === 'medium') return 'bg-yellow-100 text-yellow-800';
      if (s === 'low') return 'bg-green-100 text-green-700';

      return 'bg-gray-100 text-gray-700';
    },

    async fetchLatest() {
      this.error = null;

      try {
        const res = await fetch('/cp/safe-check/latest', {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
        });

        if (!res.ok) {
          this.error = 'Unable to load latest scan.';
          return;
        }

        const data = await res.json();
        if (data) this.scan = data;
      } catch (e) {
        // Don’t hard-fail the UI if latest fetch fails.
      }
    },

    async runScan() {

      
      this.loading = true;
      this.error = null;

      try {
        const res = await fetch('/cp/safe-check/scan', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            // Statamic CP should have CSRF token available in the page.
            'X-CSRF-TOKEN': this.getCsrfToken(),
          },
          credentials: 'same-origin',
          body: JSON.stringify({}),
        });

        const data = await res.json().catch(() => null);

        if (!res.ok) {
          this.error = data?.message || 'Scan failed. Check logs for details.';
          return;
        }

        if (data?.error) {
          this.error = data.error;
        }

        this.scan = data;
        this.$toast.success('Dependency scan completed successfully.');

      } catch (e) {
        this.error = 'Scan failed due to a network or server error.';
      } finally {
        this.loading = false;
      }
    },

    prettyDomain(url) {
  try {
    const u = new URL(url);
    return u.hostname.replace(/^www\./, '');
  } catch {
    return '';
  }
},

getCsrfToken() {
  // Statamic 6 (Blade-injected, stable)
  if (window.StatamicConfig?.csrfToken) {
    return window.StatamicConfig.csrfToken;
  }

  // Statamic 5 (legacy CP runtime config)
  if (window.Statamic?.$config?.csrfToken) {
    return window.Statamic.$config.csrfToken;
  }

  // Fallback: Laravel default (in case CP changes again)
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) {
    return meta.getAttribute('content');
  }

  throw new Error('CSRF token not found');
},


truncateUrl(url, max = 70) {
  if (!url) return '';
  return url.length > max ? url.slice(0, max) + '…' : url;
},

     async openDetails(item) {
    this.activeItem = item;
    this.activeDetails = null;
    this.modalError = null;
    this.showModal = true;
    this.modalLoading = true;

    try {
      const res = await fetch(`/cp/safe-check/vuln/${item.id}`, {
  method: 'POST',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': this.getCsrfToken(),
  },
  credentials: 'same-origin',
  body: JSON.stringify({
    package: item.package,
    installed_version: item.installed_version,
  }),
});


      if (!res.ok) {
        throw new Error('Failed to load vulnerability details.');
      }

      this.activeDetails = await res.json();
    } catch (e) {
      this.modalError = e.message || 'Unable to load vulnerability details.';
    } finally {
      this.modalLoading = false;
    }
  },

  closeModal() {
    this.showModal = false;
    this.activeItem = null;
    this.activeDetails = null;
    this.modalError = null;
    this.showFullDetails = false;
    
  },
  },
};
</script>

<style scoped>
/* Use Statamic CP styles where possible */
.card {
  background: white;
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 10px;
}

/* Statamic has btn classes, but in case your CP build doesn’t include them */
.btn {
  display: inline-flex;
  align-items: center;
  border-radius: 6px;
  padding: 10px 14px;
  border: 1px solid rgba(0,0,0,0.12);
  background: white;
  font-weight: 600;
  font-size: 14px;
}
.btn:hover { background: rgba(0,0,0,0.03); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-primary {
  display: inline-flex;
  align-items: center;
  border-radius: 6px;
  padding: 10px 14px;
  border: 1px solid rgba(0,0,0,0.12);
  background: #2563eb;
  color: white;
  font-weight: 700;
  font-size: 14px;
}
.btn-primary:hover { filter: brightness(0.95); }
.btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }


.reference-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.reference-item {
  display: block;
  padding: 10px 12px;
  border: 1px solid rgba(0,0,0,0.1);
  border-radius: 10px;
  background: #fff;
  text-decoration: none;
}

.reference-item:hover {
  background: rgba(0,0,0,0.02);
  border-color: rgba(0,0,0,0.2);
}

.reference-left {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.reference-pill {
  font-size: 12px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 999px;
  background: #eef2ff;
  color: #3730a3;
}

.reference-type {
  font-size: 12px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 999px;
  background: #f3f4f6;
  color: #374151;
}

.reference-domain {
  font-size: 13px;
  font-weight: 700;
  color: #111827;
}

.reference-url {
  font-size: 12px;
  color: #6b7280;
  word-break: break-all;
  line-height: 1.4;
}

</style>
