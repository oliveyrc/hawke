<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* @navigation/layouts/navigation.html.twig */
class __TwigTemplate_eba46103de94ca5deafebf9aadeb0a57 extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 20
        $context["control_bar_attributes"] = $this->extensions['Drupal\Core\Template\TwigExtension']->createAttribute();
        // line 21
        yield "
<div";
        // line 22
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["control_bar_attributes"] ?? null), "addClass", ["admin-toolbar-control-bar"], "method", false, false, true, 22), "setAttribute", ["data-drupal-admin-styles", ""], "method", false, false, true, 23), "html", null, true);
        // line 25
        yield ">
  <div class=\"admin-toolbar-control-bar__content\">
    ";
        // line 27
        yield from $this->load("navigation:toolbar-button", 27)->unwrap()->yield(CoreExtension::toArray(["attributes" => $this->extensions['Drupal\Core\Template\TwigExtension']->createAttribute(["aria-expanded" => "false", "aria-controls" => "admin-toolbar", "type" => "button"]), "icon" => ["icon_id" => "burger"], "text" => t("Expand sidebar"), "modifiers" => ["small-offset"], "extra_classes" => ["admin-toolbar-control-bar__burger"]]));
        // line 36
        yield "  </div>
</div>

<aside";
        // line 39
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["attributes"] ?? null), "addClass", ["admin-toolbar"], "method", false, false, true, 39), "setAttribute", ["id", "admin-toolbar"], "method", false, false, true, 40), "setAttribute", ["data-drupal-admin-styles", true], "method", false, false, true, 41), "setAttribute", ["role", "presentation"], "method", false, false, true, 42), "html", null, true);
        // line 44
        yield ">
  ";
        // line 46
        yield "  <div class=\"admin-toolbar__displace-placeholder\"></div>

  <nav class=\"admin-toolbar__scroll-wrapper\" aria-label=\"";
        // line 48
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Administrative sidebar"));
        yield "\">
    ";
        // line 49
        $context["title_menu"] = \Drupal\Component\Utility\Html::getUniqueId("admin-toolbar-title");
        // line 50
        yield "    ";
        // line 51
        yield "    <div";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["region_attributes"] ?? null), "content", [], "any", false, false, true, 51), "setAttribute", ["id", "menu-builder"], "method", false, false, true, 51), "addClass", ["admin-toolbar__content"], "method", false, false, true, 52), "html", null, true);
        // line 54
        yield ">
      ";
        // line 56
        yield "      <div class=\"admin-toolbar__header\">
        ";
        // line 57
        if ((($tmp =  !CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "hide_logo", [], "any", false, false, true, 57)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 58
            yield "          <a class=\"admin-toolbar__logo\" href=\"";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\Core\Template\TwigExtension']->getPath("<front>"));
            yield "\">
            ";
            // line 59
            if ((($tmp =  !(null === CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "logo_path", [], "any", false, false, true, 59))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                // line 60
                yield "              <img alt=\"";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Navigation logo"));
                yield "\" src=\"";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "logo_path", [], "any", false, false, true, 60), "html", null, true);
                yield "\" loading=\"eager\" width=\"";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ((CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "logo_width", [], "any", true, true, true, 60)) ? (Twig\Extension\CoreExtension::default(CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "logo_width", [], "any", false, false, true, 60), 40)) : (40)), "html", null, true);
                yield "\" height=\"";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ((CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "logo_height", [], "any", true, true, true, 60)) ? (Twig\Extension\CoreExtension::default(CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "logo_height", [], "any", false, false, true, 60), 40)) : (40)), "html", null, true);
                yield "\">
            ";
            } else {
                // line 62
                yield "              ";
                yield from $this->load("@navigation/logo.svg.twig", 62)->unwrap()->yield(CoreExtension::toArray(["label" => t("Navigation logo")]));
                // line 65
                yield "            ";
            }
            // line 66
            yield "          </a>
        ";
        }
        // line 68
        yield "        ";
        yield from $this->load("navigation:toolbar-button", 68)->unwrap()->yield(CoreExtension::toArray(["attributes" => $this->extensions['Drupal\Core\Template\TwigExtension']->createAttribute(["data-toolbar-back-control" => true, "tabindex" => "-1"]), "extra_classes" => ["admin-toolbar__back-button"], "icon" => ["icon_id" => "arrow-left"], "text" => t("Back")]));
        // line 74
        yield "        ";
        yield from $this->load("navigation:toolbar-button", 74)->unwrap()->yield(CoreExtension::toArray(["action" => t("Collapse sidebar"), "attributes" => $this->extensions['Drupal\Core\Template\TwigExtension']->createAttribute(["aria-controls" => "admin-toolbar", "tabindex" => "-1", "type" => "button"]), "extra_classes" => ["admin-toolbar__close-button"], "icon" => ["icon_id" => "cross"]]));
        // line 80
        yield "      </div>

      ";
        // line 82
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "content_top", [], "any", false, false, true, 82), "html", null, true);
        yield "
      ";
        // line 83
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "content", [], "any", false, false, true, 83), "html", null, true);
        yield "
    </div>

    ";
        // line 86
        $context["title_menu_footer"] = \Drupal\Component\Utility\Html::getUniqueId("admin-toolbar-footer");
        // line 87
        yield "    <div";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["region_attributes"] ?? null), "footer", [], "any", false, false, true, 87), "setAttribute", ["id", "menu-footer"], "method", false, false, true, 87), "addClass", ["admin-toolbar__footer"], "method", false, false, true, 88), "html", null, true);
        // line 90
        yield ">
      ";
        // line 91
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "footer", [], "any", false, false, true, 91), "html", null, true);
        yield "
      <button aria-controls=\"admin-toolbar\" class=\"admin-toolbar__expand-button\" type=\"button\">
        ";
        // line 93
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\IconsTwigExtension']->getIconRenderable("navigation", "chevron", ["class" => "admin-toolbar__expand-button-chevron", "size" => 16]), "html", null, true);
        yield "
        <span class=\"visually-hidden\" data-toolbar-text>";
        // line 94
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Collapse sidebar"));
        yield "</span>
      </button>
    </div>
  </nav>
</aside>
<div class=\"admin-toolbar-overlay\" aria-controls=\"admin-toolbar\" data-drupal-admin-styles></div>
<script>
  if (localStorage.getItem('Drupal.navigation.sidebarExpanded') !== 'false' && (window.matchMedia('(min-width: 1024px)').matches)) {
    document.documentElement.setAttribute('data-admin-toolbar', 'expanded');
  }
</script>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["attributes", "region_attributes", "settings", "content"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@navigation/layouts/navigation.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  156 => 94,  152 => 93,  147 => 91,  144 => 90,  141 => 87,  139 => 86,  133 => 83,  129 => 82,  125 => 80,  122 => 74,  119 => 68,  115 => 66,  112 => 65,  109 => 62,  97 => 60,  95 => 59,  90 => 58,  88 => 57,  85 => 56,  82 => 54,  79 => 51,  77 => 50,  75 => 49,  71 => 48,  67 => 46,  64 => 44,  62 => 39,  57 => 36,  55 => 27,  51 => 25,  49 => 22,  46 => 21,  44 => 20,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@navigation/layouts/navigation.html.twig", "/var/www/html/web/core/modules/navigation/layouts/navigation.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 20, "include" => 27, "if" => 57];
        static $filters = ["escape" => 24, "t" => 30, "clean_unique_id" => 49, "default" => 60];
        static $functions = ["create_attribute" => 20, "path" => 58, "icon" => 93];

        try {
            $this->sandbox->checkSecurity(
                ['set', 'include', 'if'],
                ['escape', 't', 'clean_unique_id', 'default'],
                ['create_attribute', 'path', 'icon'],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
