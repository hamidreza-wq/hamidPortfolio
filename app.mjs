import {
  craft as fallbackCraft,
  profile as fallbackProfile,
  projects as fallbackProjects,
} from "./portfolio-data.mjs";

let profile = window.portfolioPayload?.profile ?? fallbackProfile;
let projects = window.portfolioPayload?.projects ?? fallbackProjects;
let craft = window.portfolioPayload?.craft ?? fallbackCraft;
const loadedFonts = new Set();

function safeURL(value) {
  try {
    const url = new URL(value, window.location.origin);
    return ["http:", "https:"].includes(url.protocol) ? url.href : "#";
  } catch {
    return "#";
  }
}

const projectGrid = document.querySelector("#project-grid");
const searchInput = document.querySelector(".project-search input");
const emptyState = document.querySelector(".empty-state");
const dialog = document.querySelector(".project-dialog");
const dialogContent = document.querySelector(".dialog-content");
const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
let activeFilter = "all";

function escapeHTML(value) {
  return String(value).replace(/[&<>"']/g, (character) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[character]);
}

function renderProjects() {
  const query = searchInput.value.trim().toLowerCase();
  const visibleProjects = projects.filter((project) => {
    const matchesFilter = activeFilter === "all" || project.category === activeFilter;
    const searchable = [
      project.name,
      project.description,
      project.categoryLabel,
      ...project.technologies,
    ].join(" ").toLowerCase();
    return matchesFilter && searchable.includes(query);
  });

  projectGrid.innerHTML = visibleProjects.map((project, index) => `
    <article class="project-card reveal is-visible" style="--card-index:${index}">
      <button class="project-open" type="button" data-project="${escapeHTML(project.slug || project.repo)}" aria-label="Open details for ${escapeHTML(project.name)}">
        <span class="project-art project-art--${escapeHTML(project.visual)}" aria-hidden="true">
          ${project.image ? `<img class="project-card-image" src="${escapeHTML(safeURL(project.image.url))}" alt="">` : ""}
          <span class="art-orbit art-orbit--one"></span>
          <span class="art-orbit art-orbit--two"></span>
          <span class="art-core"></span>
          <span class="art-caption">${escapeHTML(project.visualLabel)}</span>
          <span class="art-index">0${index + 1}</span>
        </span>
        <span class="project-card-body">
          <span class="project-category">${escapeHTML(project.categoryLabel)}${project.featured ? " · Featured" : ""}${project.status ? ` · ${escapeHTML(project.status)}` : ""}</span>
          <span class="project-title-line"><strong>${escapeHTML(project.name)}</strong><span class="project-arrow" aria-hidden="true">↗</span></span>
          <span class="project-description">${escapeHTML(project.description)}</span>
          <span class="project-tags">${project.technologies.slice(0, 4).map((tech) => `<span>${escapeHTML(tech)}</span>`).join("")}</span>
        </span>
      </button>
      ${project.detailUrl ? `<a class="project-detail-link" href="${escapeHTML(safeURL(project.detailUrl))}">Project page <span aria-hidden="true">↗</span></a>` : ""}
    </article>
  `).join("");
  emptyState.hidden = visibleProjects.length > 0;
  projectGrid.hidden = visibleProjects.length === 0;
}

function renderCraft() {
  document.querySelector("#craft-groups").innerHTML = craft.map((group, index) => `
    <section class="craft-group reveal is-visible" style="--group-index:${index}">
      <h3><span>0${index + 1}</span>${escapeHTML(group.title)}</h3>
      <ul>${group.items.map((item) => `<li>${escapeHTML(item)}</li>`).join("")}</ul>
    </section>
  `).join("");
}

function renderContact() {
  document.querySelector("#contact-actions").innerHTML = `
    ${profile.email ? `<a class="button button-primary" href="mailto:${encodeURIComponent(profile.email)}">Email me <span aria-hidden="true">↗</span></a>` : ""}
    <a class="button ${profile.email ? "button-quiet" : "button-primary"}" href="${escapeHTML(profile.github)}" target="_blank" rel="noreferrer">
      Connect on GitHub <span aria-hidden="true">↗</span>
    </a>
    ${profile.linkedin ? `<a class="button button-quiet" href="${escapeHTML(profile.linkedin)}" target="_blank" rel="noreferrer">LinkedIn <span aria-hidden="true">↗</span></a>` : ""}
  `;
  const contactSlot = document.querySelector("#contact-form-slot");
  const actionUrl = window.portfolioPayload?.settings?.contact_action_url;
  const token = window.portfolioPayload?.settings?.csrf_token;
  if (!contactSlot || !actionUrl || !token) return;
  const feedback = window.portfolioPayload?.settings?.contact_feedback;
  contactSlot.innerHTML = `
    <form class="portfolio-contact-form" method="post" action="${escapeHTML(actionUrl)}">
      <input type="hidden" name="_token" value="${escapeHTML(token)}">
      <input type="hidden" name="submission_token" value="${escapeHTML(window.portfolioPayload?.settings?.contact_submission_token ?? "")}">
      <label class="contact-honeypot" aria-hidden="true">Leave this field empty<input name="website" tabindex="-1" autocomplete="off"></label>
      <div class="contact-form-heading"><strong>Send a note</strong><span>Private · goes directly to the studio inbox</span></div>
      <div class="contact-form-grid">
        <label>Your name<input name="name" autocomplete="name" maxlength="120" required></label>
        <label>Email address<input name="email" type="email" autocomplete="email" maxlength="255" required></label>
        <label class="contact-form-wide">Subject<input name="subject" maxlength="150" required></label>
        <label class="contact-form-wide">Message<textarea name="message" rows="4" maxlength="5000" required></textarea></label>
      </div>
      <div class="contact-form-submit"><button class="button button-primary" type="submit">Send message <span aria-hidden="true">↗</span></button><span class="contact-form-feedback" role="status" aria-live="polite">${feedback ? escapeHTML(feedback) : ""}</span></div>
    </form>
  `;
  if (window.portfolioPreview === true) {
    contactSlot.querySelector("form").addEventListener("submit", (event) => {
      event.preventDefault();
      contactSlot.querySelector(".contact-form-feedback").textContent = "Preview only — publishing the page will not send a message.";
    });
  }
}

function applyPortfolioData(data) {
  profile = data.profile ?? profile;
  projects = data.projects ?? projects;
  craft = data.craft ?? craft;

  const managedValues = {
    "profile.name": profile.name,
    "profile.title": profile.title,
    "profile.footer": data.settings?.footer_text ?? "",
  };
  for (const section of data.sections ?? []) {
    managedValues[`section.${section.key}.title`] = section.title;
    managedValues[`section.${section.key}.subtitle`] = section.subtitle ?? "";
    managedValues[`section.${section.key}.body`] = section.body ?? "";
    if (section.key === "home") managedValues["section.home.body"] = (section.body ?? "").replace(/^I['’]m [^—\r\n]{1,120}\s*—\s*/u, "");
  }

  document.querySelectorAll("[data-managed]").forEach((element) => {
    const value = managedValues[element.dataset.managed];
    if (typeof value !== "string") return;
    const copy = element.cloneNode(true);
    copy.querySelectorAll("br").forEach((lineBreak) => lineBreak.replaceWith(" "));
    if (copy.textContent.trim().replace(/\s+/g, " ") === value.trim().replace(/\s+/g, " ")) return;
    element.textContent = value;
    if (element.dataset.managed === "section.home.title") {
      const parts = value.match(/^(.*\s)(\S+)$/s);
      if (parts) {
        const accent = document.createElement("span");
        accent.className = "gradient-text";
        accent.textContent = parts[2];
        element.replaceChildren(document.createTextNode(parts[1]), accent);
      }
    }
  });
  document.querySelectorAll('[data-managed-href="profile.github"]').forEach((element) => {
    if (profile.github) element.href = profile.github;
  });
  document.querySelectorAll('[data-managed-href="profile.github.repositories"]').forEach((element) => {
    const href = safeURL(profile.github);
    if (href === "#") return;
    const url = new URL(href);
    if (url.protocol !== "https:" || url.hostname.toLowerCase() !== "github.com") return;
    url.search = "?tab=repositories";
    url.hash = "";
    element.href = url.href;
  });
  if (typeof profile.name === "string" && profile.name.trim()) {
    const initial = Array.from(profile.name.trim())[0].toLocaleUpperCase();
    document.querySelectorAll('[data-managed-initial="profile.name"]').forEach((element) => {
      element.textContent = initial;
    });
    document.querySelectorAll('[data-managed-label="profile.name"]').forEach((element) => {
      element.setAttribute("aria-label", `${profile.name.trim()}, home`);
    });
  }

  const theme = data.theme ?? {};
  const root = document.documentElement;
  if (/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})*$/.test(data.settings?.language ?? "")) root.lang = data.settings.language;
  for (const font of data.fonts ?? []) {
    if (!/^PortfolioFont\d+$/.test(font.css_family ?? "") || loadedFonts.has(font.css_family)) continue;
    if (!/^\d{3}$/.test(String(font.weight)) || !["normal", "italic", "oblique"].includes(font.style)) continue;
    const source = safeURL(font.url);
    if (source === "#") continue;
    const face = new FontFace(font.css_family, `url("${source}")`, { weight: String(font.weight), style: font.style });
    loadedFonts.add(font.css_family);
    face.load().then((loaded) => document.fonts.add(loaded)).catch(() => loadedFonts.delete(font.css_family));
  }
  const colorProperties = {
    background: "--night",
    foreground: "--ink",
    primary: "--violet",
    accent: "--accent",
    cyan: "--cyan",
  };
  Object.entries(colorProperties).forEach(([key, property]) => {
    if (/^#[0-9a-f]{6}$/i.test(theme[key] ?? "")) root.style.setProperty(property, theme[key]);
  });
  if (/^[\w ,_-]{1,60}$/.test(theme.font_body ?? "")) root.style.setProperty("--sans", `${theme.font_body}, system-ui, sans-serif`);
  if (/^[\w ,_-]{1,60}$/.test(theme.font_heading ?? "")) root.style.setProperty("--heading", `${theme.font_heading}, system-ui, sans-serif`);
  if (Number.isFinite(Number(theme.max_width))) root.style.setProperty("--content-width", `${Math.min(1600, Math.max(720, Number(theme.max_width)))}px`);
  if (Number.isFinite(Number(theme.section_spacing))) root.style.setProperty("--section-spacing", `${Math.min(240, Math.max(48, Number(theme.section_spacing)))}px`);
  if (Number.isFinite(Number(theme.border_radius))) root.style.setProperty("--card-radius", `${Math.min(32, Math.max(0, Number(theme.border_radius)))}px`);
  document.body.dataset.motion = theme.animations_enabled === false || theme.animations_enabled === 0 ? "off" : "on";
  document.querySelector(".particle-field").hidden = theme.particles_enabled === false || theme.particles_enabled === 0;

  const sectionElements = {
    home: document.querySelector("#home"),
    about: document.querySelector("#about"),
    work: document.querySelector("#work"),
    craft: document.querySelector("#craft"),
    journey: document.querySelector("#journey"),
    contact: document.querySelector("#contact"),
  };
  document.querySelectorAll("[data-custom-section]").forEach((element) => element.remove());
  for (const section of data.sections ?? []) {
    if (sectionElements[section.key] || !/^[a-z0-9-]+$/.test(section.key) || !["experience", "education", "services", "testimonials", "custom"].includes(section.type)) continue;
    const element = document.createElement("section");
    element.id = section.key;
    element.className = "content-section section-shell managed-custom-section";
    element.dataset.customSection = "";
    element.innerHTML = `<div class="section-heading"><p class="eyebrow">${escapeHTML(section.subtitle ?? "")}</p><h2>${escapeHTML(section.title ?? "")}</h2><p>${escapeHTML(section.body ?? "")}</p></div>
      ${section.image ? `<img class="section-image" src="${escapeHTML(safeURL(section.image.url))}" alt="${escapeHTML(section.image.alt ?? "")}" loading="lazy">` : ""}
      <div class="content-items">${(section.items ?? []).map((item) => `<article class="content-item"><h3>${escapeHTML(item.title ?? "")}</h3><p class="eyebrow">${escapeHTML(item.subtitle ?? "")}</p><p>${escapeHTML(item.body ?? "")}</p></article>`).join("")}</div>`;
    sectionElements[section.key] = element;
  }
  if (Array.isArray(data.sections) && data.sections.length) {
    const main = document.querySelector("#main");
    const home = sectionElements.home;
    const strip = main.querySelector(".intro-strip");
    const activeSections = data.sections
      .filter((section) => sectionElements[section.key])
      .sort((left, right) => left.sort_order - right.sort_order);
    Object.values(sectionElements).forEach((element) => {
      if (element) element.hidden = true;
    });
    activeSections.forEach((section) => {
      const element = sectionElements[section.key];
      element.hidden = section.is_visible === false || section.is_visible === 0;
      const spacingScale = Math.max(48, Math.min(240, Number(theme.section_spacing ?? 140))) / 140;
      element.style.paddingTop = `${Math.max(0, Math.min(240, Number(section.spacing_top ?? 100))) * spacingScale}px`;
      element.style.paddingBottom = `${Math.max(0, Math.min(240, Number(section.spacing_bottom ?? 100))) * spacingScale}px`;
      element.dataset.backgroundEffect = section.background_effect ?? "cosmic";
      element.style.backgroundImage = section.background ? `url("${safeURL(section.background.url)}")` : "";
      if (["home", "about", "work", "craft", "journey", "contact"].includes(section.key)) {
        element.querySelector("[data-section-image]")?.remove();
        if (section.image) {
          const image = document.createElement("img");
          image.className = "section-image";
          image.dataset.sectionImage = "";
          image.src = safeURL(section.image.url);
          image.alt = section.image.alt ?? "";
          image.loading = "lazy";
          element.append(image);
        }
      }
    });
    activeSections.forEach((section) => {
      main.append(sectionElements[section.key]);
      if (section.key === "home" && strip) {
        strip.hidden = sectionElements.home.hidden;
        main.append(strip);
      }
    });
    const nav = document.querySelector(".primary-navigation");
    nav.innerHTML = activeSections.filter((section) => section.is_visible && section.show_in_navigation !== false && section.show_in_navigation !== 0)
      .map((section) => `<a href="#${escapeHTML(section.key)}">${escapeHTML(section.label || section.title)}</a>`).join("");
  }

  renderProjects();
  renderCraft();
  renderContact();
}

function showProject(project) {
  dialogContent.innerHTML = `
    <p class="eyebrow">${escapeHTML(project.categoryLabel)} <span class="dialog-index">/ REPOSITORY NOTES</span></p>
    <h2 id="dialog-title">${escapeHTML(project.name)}</h2>
    <p class="dialog-description">${escapeHTML(project.detail)}</p>
    ${project.image ? `<img class="project-featured-image" src="${escapeHTML(safeURL(project.image.url))}" alt="${escapeHTML(project.image.alt ?? "")}">` : ""}
    ${(project.gallery ?? []).length ? `<div class="project-gallery">${project.gallery.map((image) => `<img src="${escapeHTML(safeURL(image.url))}" alt="${escapeHTML(image.alt ?? "")}" loading="lazy">`).join("")}</div>` : ""}
    <h3>Built with</h3>
    <ul class="dialog-tags">${project.technologies.map((tech) => `<li>${escapeHTML(tech)}</li>`).join("")}</ul>
    <h3>Inside the project</h3>
    <ul class="dialog-features">${project.features.map((feature) => `<li>${escapeHTML(feature)}</li>`).join("")}</ul>
    <a class="button button-primary dialog-repo" href="${escapeHTML(safeURL(project.repositoryUrl ?? `https://github.com/hamidreza-wq/${encodeURIComponent(project.repo)}`))}" target="_blank" rel="noreferrer">
      View repository <span aria-hidden="true">↗</span>
    </a>
    ${project.liveUrl ? `<a class="button button-quiet" href="${escapeHTML(safeURL(project.liveUrl))}" target="_blank" rel="noreferrer">Live demo ↗</a>` : ""}
    ${project.detailUrl ? `<a class="button button-quiet" href="${escapeHTML(safeURL(project.detailUrl))}">Indexable project page ↗</a>` : ""}
  `;
  dialog.showModal();
}

document.querySelectorAll(".filter-button").forEach((button) => {
  button.addEventListener("click", () => {
    activeFilter = button.dataset.filter;
    document.querySelectorAll(".filter-button").forEach((filterButton) => {
      const selected = filterButton === button;
      filterButton.classList.toggle("is-active", selected);
      filterButton.setAttribute("aria-pressed", String(selected));
    });
    renderProjects();
  });
});

searchInput.addEventListener("input", renderProjects);
projectGrid.addEventListener("click", (event) => {
  const trigger = event.target.closest("[data-project]");
  if (!trigger) return;
  const project = projects.find((item) => (item.slug || item.repo) === trigger.dataset.project);
  if (project) showProject(project);
});

document.querySelector(".dialog-close").addEventListener("click", () => dialog.close());
dialog.addEventListener("keydown", (event) => {
  if (event.key !== "Escape") return;
  event.preventDefault();
  dialog.close();
});
dialog.addEventListener("click", (event) => {
  if (event.target === dialog) dialog.close();
});

if (window.portfolioPayload) applyPortfolioData(window.portfolioPayload);
else {
  renderProjects();
  renderCraft();
  renderContact();
}

if (window.portfolioPreview === true) {
  window.addEventListener("message", (event) => {
    if (event.source !== window.parent || event.origin !== window.location.origin) return;
    if (event.data?.type !== "portfolio:preview" || !event.data.payload) return;
    applyPortfolioData(event.data.payload);
  });
}

const menuToggle = document.querySelector(".menu-toggle");
const navigation = document.querySelector(".primary-navigation");
menuToggle.addEventListener("click", () => {
  const isExpanded = menuToggle.getAttribute("aria-expanded") === "true";
  menuToggle.setAttribute("aria-expanded", String(!isExpanded));
  menuToggle.setAttribute("aria-label", isExpanded ? "Open navigation" : "Close navigation");
  navigation.classList.toggle("is-open", !isExpanded);
});

navigation.addEventListener("click", (event) => {
  if (event.target.closest("a")) {
    menuToggle.setAttribute("aria-expanded", "false");
    menuToggle.setAttribute("aria-label", "Open navigation");
    navigation.classList.remove("is-open");
  }
});

const progressBar = document.querySelector(".scroll-progress");
let scrollFrame = 0;
window.addEventListener("scroll", () => {
  if (scrollFrame) return;
  scrollFrame = window.requestAnimationFrame(() => {
    const scrollable = document.documentElement.scrollHeight - window.innerHeight;
    progressBar.style.transform = `scaleX(${scrollable > 0 ? window.scrollY / scrollable : 0})`;
    scrollFrame = 0;
  });
}, { passive: true });

const revealObserver = new IntersectionObserver((entries, observer) => {
  entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    entry.target.classList.add("is-visible");
    observer.unobserve(entry.target);
  });
}, { threshold: 0.14 });

if (!reducedMotion) {
  document.querySelectorAll(".reveal:not(.is-visible)").forEach((element) => revealObserver.observe(element));
} else {
  document.querySelectorAll(".reveal").forEach((element) => element.classList.add("is-visible"));
}

const canvas = document.querySelector(".particle-field");
const context = canvas.getContext("2d", { alpha: true });
const scene = document.querySelector(".universe-scene");
const pointer = { x: 0, y: 0, targetX: 0, targetY: 0 };
let particles = [];
let animationFrame = 0;
let width = 0;
let height = 0;

function resizeCanvas() {
  const ratio = Math.min(window.devicePixelRatio || 1, 1.5);
  width = window.innerWidth;
  height = window.innerHeight;
  canvas.width = Math.round(width * ratio);
  canvas.height = Math.round(height * ratio);
  canvas.style.width = `${width}px`;
  canvas.style.height = `${height}px`;
  context.setTransform(ratio, 0, 0, ratio, 0, 0);

  const count = Math.min(reducedMotion ? 36 : 90, Math.round(width * height / 14500));
  particles = Array.from({ length: count }, () => ({
    x: Math.random() * width,
    y: Math.random() * height,
    radius: Math.random() * 1.25 + 0.25,
    alpha: Math.random() * 0.5 + 0.15,
    drift: (Math.random() - 0.5) * 0.12,
    phase: Math.random() * Math.PI * 2,
  }));
  if (reducedMotion) drawParticles(0);
}

function drawParticles(time) {
  pointer.x += (pointer.targetX - pointer.x) * 0.04;
  pointer.y += (pointer.targetY - pointer.y) * 0.04;
  scene.style.setProperty("--pointer-x", `${pointer.x.toFixed(3)}`);
  scene.style.setProperty("--pointer-y", `${pointer.y.toFixed(3)}`);
  context.clearRect(0, 0, width, height);
  particles.forEach((particle, index) => {
    const shimmer = reducedMotion ? 1 : 0.72 + Math.sin(time / 1300 + particle.phase) * 0.28;
    const shiftX = pointer.x * (index % 4 + 1) * 0.8;
    const shiftY = pointer.y * (index % 3 + 1) * 0.65;
    particle.y += reducedMotion ? 0 : particle.drift;
    if (particle.y > height + 2) particle.y = -2;
    if (particle.y < -2) particle.y = height + 2;
    context.beginPath();
    context.fillStyle = `rgba(212, 202, 255, ${particle.alpha * shimmer})`;
    context.arc(particle.x + shiftX, particle.y + shiftY, particle.radius, 0, Math.PI * 2);
    context.fill();
  });
  if (!reducedMotion && !document.hidden) animationFrame = requestAnimationFrame(drawParticles);
}

function setPointer(event) {
  pointer.targetX = (event.clientX / window.innerWidth - 0.5) * 2;
  pointer.targetY = (event.clientY / window.innerHeight - 0.5) * 2;
}

if (context) {
  resizeCanvas();
  if (!reducedMotion) animationFrame = requestAnimationFrame(drawParticles);
  window.addEventListener("resize", resizeCanvas, { passive: true });
  window.addEventListener("pointermove", setPointer, { passive: true });
  document.addEventListener("visibilitychange", () => {
    if (document.hidden) cancelAnimationFrame(animationFrame);
    else if (!reducedMotion) animationFrame = requestAnimationFrame(drawParticles);
  });
}
