<?php 

/******************************************************
  * Corrige el string del tiempo del evento, en el caso que 
  * �ste sea de longitud 1, representaci�n de 0-9, a�adiendo un 
  * 0 como primer car�cter del string.
  *
  * @param $t: string 
  * @return string
  *******************************************************/
  
function time_b($t)
{
    if (isset($t))
    {
        $t=(string)$t;
    
        switch (strlen($t))
        {
            case 0: $t='00';break;
            case 1: $t='0'.$t;
        }
    }
return $t;
    
    }


/*************************************************************
  * Substituye <, > por &lt; y &gt;, respectivamente.
  * En necesario para definir correctamente la informaci�n del evento.
  *
  * @param $s: string 
  * @return string
  **************************************************************/
function correct_syntax($s)
{
    if (isset($s)){
        $s=str_replace('<','&lt;',$s);
        $s=str_replace('>','&gt;',$s);
    }
    return $s;
}

?>