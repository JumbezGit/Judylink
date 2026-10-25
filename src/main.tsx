import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'
import './index.css'

const storedTheme = window.localStorage.getItem('judylink-theme')
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
const theme = storedTheme === 'dark' || storedTheme === 'light'
  ? storedTheme
  : prefersDark ? 'dark' : 'light'

document.documentElement.classList.toggle('dark', theme === 'dark')
document.documentElement.style.colorScheme = theme

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)

if ('serviceWorker' in navigator && import.meta.env.PROD) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch((error) => {
      console.error('Service worker registration failed:', error)
    })
  })
}



