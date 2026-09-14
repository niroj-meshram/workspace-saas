/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Origin of the Laravel API, e.g. http://localhost:8000 */
  readonly VITE_API_URL: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
