<?php
/*
 * Classes.php
 * Domain model classes for the EmpDept Management System.
 * These are plain data objects; DB operations live in Functions.php.
 *
 * Project by Shahryar Ahmad
 */

/* -----------------------------------------------
   Class for the DEPT table
----------------------------------------------- */
class Dept {
    private $deptno;
    private $dname;
    private $loc;

    public function __construct($deptno = null, $dname = null, $loc = null) {
        $this->deptno = $deptno;
        $this->dname  = $dname;
        $this->loc    = $loc;
    }

    public function get_deptno() { return $this->deptno; }
    public function get_dname()  { return $this->dname;  }
    public function get_loc()    { return $this->loc;    }

    public function set_deptno($v) { $this->deptno = $v; }
    public function set_dname($v)  { $this->dname  = $v; }
    public function set_loc($v)    { $this->loc    = $v; }
}

/* -----------------------------------------------
   Class for the EMP table
----------------------------------------------- */
class Emp {
    private $empno;
    private $ename;
    private $job;
    private $mgr;
    private $hiredate;
    private $sal;
    private $comm;
    private $deptno;

    public function __construct(
        $empno    = null, $ename    = null, $job   = null, $mgr  = null,
        $hiredate = null, $sal      = null, $comm  = null, $deptno = null
    ) {
        $this->empno    = $empno;
        $this->ename    = $ename;
        $this->job      = $job;
        $this->mgr      = $mgr;
        $this->hiredate = $hiredate;
        $this->sal      = $sal;
        $this->comm     = $comm;
        $this->deptno   = $deptno;
    }

    public function get_empno()   { return $this->empno;   }
    public function get_ename()   { return $this->ename;   }
    public function get_job()     { return $this->job;     }
    public function get_mgr()     { return $this->mgr;     }
    public function get_hiredate(){ return $this->hiredate;}
    public function get_sal()     { return $this->sal;     }
    public function get_comm()    { return $this->comm;    }
    public function get_deptno()  { return $this->deptno;  }

    public function set_ename($v)   { $this->ename   = $v; }
    public function set_job($v)     { $this->job     = $v; }
    public function set_mgr($v)     { $this->mgr     = $v; }
    public function set_sal($v)     { $this->sal     = $v; }
    public function set_comm($v)    { $this->comm    = $v; }
    public function set_deptno($v)  { $this->deptno  = $v; }
}

/* -----------------------------------------------
   Class for the SALGRADE table
----------------------------------------------- */
class Salgrade {
    private $grade;
    private $losal;
    private $hisal;

    public function __construct($grade = null, $losal = null, $hisal = null) {
        $this->grade = $grade;
        $this->losal = $losal;
        $this->hisal = $hisal;
    }

    public function get_grade() { return $this->grade; }
    public function get_losal() { return $this->losal; }
    public function get_hisal() { return $this->hisal; }
}

/* -----------------------------------------------
   Class for the BONUS table
----------------------------------------------- */
class Bonus {
    private $ename;
    private $job;
    private $sal;
    private $comm;

    public function __construct($ename = null, $job = null, $sal = null, $comm = null) {
        $this->ename = $ename;
        $this->job   = $job;
        $this->sal   = $sal;
        $this->comm  = $comm;
    }

    public function get_ename() { return $this->ename; }
    public function get_job()   { return $this->job;   }
    public function get_sal()   { return $this->sal;   }
    public function get_comm()  { return $this->comm;  }
}

/* -----------------------------------------------
   Class for the Project table
----------------------------------------------- */
class Project {
    private $projno;
    private $projname;
    private $projtype;
    private $startdate;
    private $enddate;
    private $managerno;
    private $hrsrate;

    public function __construct(
        $projno = null, $projname = null, $projtype   = null,
        $startdate = null, $enddate = null, $managerno = null, $hrsrate = null
    ) {
        $this->projno    = $projno;
        $this->projname  = $projname;
        $this->projtype  = $projtype;
        $this->startdate = $startdate;
        $this->enddate   = $enddate;
        $this->managerno = $managerno;
        $this->hrsrate   = $hrsrate;
    }

    public function get_projno()    { return $this->projno;    }
    public function get_projname()  { return $this->projname;  }
    public function get_projtype()  { return $this->projtype;  }
    public function get_startdate() { return $this->startdate; }
    public function get_enddate()   { return $this->enddate;   }
    public function get_managerno() { return $this->managerno; }
    public function get_hrsrate()   { return $this->hrsrate;   }
}

/* -----------------------------------------------
   Class for the ProjAssign table
----------------------------------------------- */
class ProjAssign {
    private $projno;
    private $empno;
    private $projperiod;
    private $noofhrs;

    public function __construct($projno = null, $empno = null, $projperiod = null, $noofhrs = null) {
        $this->projno     = $projno;
        $this->empno      = $empno;
        $this->projperiod = $projperiod;
        $this->noofhrs    = $noofhrs;
    }

    public function get_projno()     { return $this->projno;     }
    public function get_empno()      { return $this->empno;      }
    public function get_projperiod() { return $this->projperiod; }
    public function get_noofhrs()    { return $this->noofhrs;    }
}
?>